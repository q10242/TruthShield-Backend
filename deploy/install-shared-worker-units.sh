#!/usr/bin/env bash
set -euo pipefail

sudo install -d -m 0755 /etc/truthshield

if [[ ! -f /path/to/worker.env ]]; then
  sudo install -m 0600 deploy/shared-worker.env.example /path/to/worker.env
  echo "Created /path/to/worker.env. Fill real production values before starting services." >&2
fi

sudo install -m 0644 deploy/truthshield-worker.service /etc/systemd/system/truthshield-worker.service
sudo install -m 0644 deploy/truthshield-scheduler.cron /etc/cron.d/truthshield-scheduler
sudo systemctl daemon-reload

echo "Installed worker service and scheduler cron."
