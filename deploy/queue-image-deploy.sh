#!/usr/bin/env bash
set -euo pipefail

IMAGE="${IMAGE:?Set IMAGE to the Artifact Registry image}"
CONTAINER_NAME="${CONTAINER_NAME:-truthshield-worker}"
ENV_FILE="${ENV_FILE:-/path/to/worker.env}"
DOCKER_NETWORK="${DOCKER_NETWORK:-}"
QUEUE_CONNECTION="${QUEUE_CONNECTION:-redis}"
QUEUE_NAME="${QUEUE_NAME:-default}"
QUEUE_SLEEP="${QUEUE_SLEEP:-1}"
QUEUE_TRIES="${QUEUE_TRIES:-3}"
QUEUE_TIMEOUT="${QUEUE_TIMEOUT:-90}"
QUEUE_MEMORY="${QUEUE_MEMORY:-256}"
SCHEDULE_CRON_FILE="${SCHEDULE_CRON_FILE:-/etc/cron.d/truthshield-scheduler}"
IMAGE_CLEANUP_KEEP="${IMAGE_CLEANUP_KEEP:-3}"

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "Missing ${ENV_FILE} on queue host." >&2
  exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
  echo "docker is required on the queue host." >&2
  exit 1
fi

echo "Deploying ${IMAGE} to ${CONTAINER_NAME}"

NETWORK_ARGS=()
if [[ -n "${DOCKER_NETWORK}" ]]; then
  NETWORK_ARGS=(--network "${DOCKER_NETWORK}")
fi

REGISTRY_HOST="${IMAGE%%/*}"
if command -v gcloud >/dev/null 2>&1; then
  gcloud auth configure-docker "${REGISTRY_HOST}" --quiet >/dev/null 2>&1 || true
fi

docker rm -f "${CONTAINER_NAME}" >/dev/null 2>&1 || true
docker image rm "${IMAGE}" >/dev/null 2>&1 || true
docker pull "${IMAGE}"

docker run -d \
  --name "${CONTAINER_NAME}" \
  --restart unless-stopped \
  "${NETWORK_ARGS[@]}" \
  --env-file "${ENV_FILE}" \
  "${IMAGE}" \
  sh -lc "sleep infinity"

docker exec "${CONTAINER_NAME}" php artisan optimize:clear
docker exec "${CONTAINER_NAME}" php artisan migrate --force
docker exec "${CONTAINER_NAME}" php artisan db:seed --class=TagSeeder --force
docker exec "${CONTAINER_NAME}" php artisan truthshield:seed-launch-policies
docker exec "${CONTAINER_NAME}" php artisan truthshield:ensure-algorithm-version
docker exec "${CONTAINER_NAME}" php artisan config:cache
docker exec "${CONTAINER_NAME}" php artisan route:cache
docker exec "${CONTAINER_NAME}" php artisan view:cache

docker rm -f "${CONTAINER_NAME}" >/dev/null

docker run -d \
  --name "${CONTAINER_NAME}" \
  --restart unless-stopped \
  "${NETWORK_ARGS[@]}" \
  --env-file "${ENV_FILE}" \
  "${IMAGE}" \
  sh -lc "php artisan queue:work ${QUEUE_CONNECTION} --queue=${QUEUE_NAME} --sleep=${QUEUE_SLEEP} --tries=${QUEUE_TRIES} --timeout=${QUEUE_TIMEOUT} --memory=${QUEUE_MEMORY}"

if [[ -w "$(dirname "${SCHEDULE_CRON_FILE}")" ]]; then
  cat > "${SCHEDULE_CRON_FILE}" <<CRON
* * * * * root docker exec ${CONTAINER_NAME} php artisan schedule:run >> /var/log/truthshield-schedule.log 2>&1
CRON
  chmod 0644 "${SCHEDULE_CRON_FILE}"
elif command -v sudo >/dev/null 2>&1; then
  tmp="$(mktemp)"
  cat > "${tmp}" <<CRON
* * * * * root docker exec ${CONTAINER_NAME} php artisan schedule:run >> /var/log/truthshield-schedule.log 2>&1
CRON
  sudo install -m 0644 "${tmp}" "${SCHEDULE_CRON_FILE}"
  rm -f "${tmp}"
else
  echo "Cannot write ${SCHEDULE_CRON_FILE}; install scheduler cron manually." >&2
fi

docker exec "${CONTAINER_NAME}" php artisan truthshield:record-operational-heartbeat queue_worker || true

if [[ "${IMAGE_CLEANUP_KEEP}" =~ ^[0-9]+$ && "${IMAGE_CLEANUP_KEEP}" -gt 0 ]]; then
  IMAGE_REPOSITORY="${IMAGE%:*}"
  current_image_id="$(docker image inspect "${IMAGE}" --format '{{.Id}}' 2>/dev/null || true)"
  mapfile -t old_image_ids < <(
    docker images "${IMAGE_REPOSITORY}" \
      --format '{{.ID}} {{.CreatedAt}}' \
      | sort -k2,3r \
      | awk -v keep="${IMAGE_CLEANUP_KEEP}" 'NR > keep { print $1 }' \
      | sort -u
  )

  for image_id in "${old_image_ids[@]}"; do
    if [[ -n "${current_image_id}" && "${image_id}" == "${current_image_id#sha256:}" ]]; then
      continue
    fi

    docker image rm "${image_id}" >/dev/null 2>&1 || true
  done

  docker image prune -f >/dev/null 2>&1 || true
fi

echo "Queue worker deployed: ${CONTAINER_NAME}"
