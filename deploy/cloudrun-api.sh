#!/usr/bin/env bash
set -euo pipefail

PROJECT_ID="${PROJECT_ID:?Set PROJECT_ID}"
REGION="${REGION:-asia-east1}"
SERVICE="${SERVICE:-truth-shield-api}"
REPOSITORY="${REPOSITORY:-truthshield}"
ENV_FILE="${ENV_FILE:-deploy/cloudrun-api.env.yaml}"
IMAGE_TAG="${IMAGE_TAG:-$(git rev-parse --short HEAD)}"
IMAGE="${REGION}-docker.pkg.dev/${PROJECT_ID}/${REPOSITORY}/${SERVICE}:${IMAGE_TAG}"

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "Missing ${ENV_FILE}. Copy deploy/cloudrun-api.env.example.yaml and fill production values." >&2
  exit 1
fi

gcloud config set project "${PROJECT_ID}" >/dev/null

if ! gcloud artifacts repositories describe "${REPOSITORY}" --location="${REGION}" >/dev/null 2>&1; then
  gcloud artifacts repositories create "${REPOSITORY}" \
    --repository-format=docker \
    --location="${REGION}" \
    --description="TruthShield containers"
fi

gcloud builds submit . --tag "${IMAGE}"

DEPLOY_ARGS=(
  run deploy "${SERVICE}"
  --image "${IMAGE}"
  --region "${REGION}"
  --platform managed
  --allow-unauthenticated
  --port 8000
  --memory "${MEMORY:-1Gi}"
  --cpu "${CPU:-1}"
  --min-instances "${MIN_INSTANCES:-0}"
  --max-instances "${MAX_INSTANCES:-20}"
  --timeout "${TIMEOUT:-300}"
  --concurrency "${CONCURRENCY:-80}"
  --env-vars-file "${ENV_FILE}"
)

if [[ -n "${CLOUDSQL_INSTANCE:-}" ]]; then
  DEPLOY_ARGS+=(--add-cloudsql-instances "${CLOUDSQL_INSTANCE}")
fi

gcloud "${DEPLOY_ARGS[@]}"

echo "Deployed ${SERVICE}: ${IMAGE}"
