#!/usr/bin/env bash
set -euo pipefail

#############################################
# new_deploy.sh — update a running Docker deployment
#
#   git pull --ff-only && ./scripts/new_deploy.sh [--pull]
#
# Runs the preflight (.env, APP_KEY, free port), rebuilds the app image and
# recreates the containers. The database (db_data) and uploads (app_storage)
# volumes are preserved; migrations run additively from the entrypoint.
#   --pull   also pull the latest MySQL base image
#############################################

log()  { echo -e "\n\033[1;32m[INFO]\033[0m $*"; }
warn() { echo -e "\n\033[1;33m[WARN]\033[0m $*"; }
err()  { echo -e "\n\033[1;31m[ERR ]\033[0m $*" >&2; }
die()  { err "$*"; exit 1; }

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

log "=== assets.i-portal.me — Docker deploy ==="
command -v docker >/dev/null 2>&1 || die "Docker is not installed."
docker compose version >/dev/null 2>&1 || die "Docker Compose v2 plugin not found."
[[ -f docker-compose.yml ]] || die "docker-compose.yml not found at ${REPO_ROOT}"

# shellcheck disable=SC1091
source "${REPO_ROOT}/scripts/docker-preflight.sh"
docker_preflight

warn "Volumes db_data (MySQL) and app_storage (uploads) are preserved."

if [[ "${1:-}" == "--pull" ]]; then
  log "Pulling latest base images..."
  docker compose pull
fi

log "Rebuilding the app image and recreating containers..."
docker compose up -d --build

log "Waiting for the app to answer..."
for _ in $(seq 1 30); do
  if docker compose exec -T app php artisan about --only=environment >/dev/null 2>&1; then break; fi
  sleep 2
done

docker image prune -f >/dev/null 2>&1 || true
docker compose ps

log "DEPLOY DONE."
echo "-------------------------------------------"
echo "URL:      $(env_get APP_URL)   (host port $(env_get WEB_PORT))"
echo "Logs:     docker compose logs -f app"
echo "-------------------------------------------"
