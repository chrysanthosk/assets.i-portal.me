#!/usr/bin/env bash
set -euo pipefail

#############################################
# backup-db.sh
# Dumps the Dockerised MySQL database (compose service "db") to a gzip file
# and prunes old backups.
#
#   ./scripts/backup-db.sh
#
# Env overrides:
#   BACKUP_DIR=backups        where dumps are written
#   RETENTION_DAYS=14         delete dumps older than this many days
#############################################

# Run from the repo root (this script lives in scripts/).
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

BACKUP_DIR="${BACKUP_DIR:-backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"

# Read a single key from .env without sourcing the whole file (avoids quoting issues).
env_val() {
  local key="$1"
  # `|| true` so a missing key doesn't abort the script under `set -e`.
  { grep -E "^${key}=" .env 2>/dev/null | tail -1 | cut -d= -f2- | sed 's/^"//; s/"$//'; } || true
}

DB_NAME="$(env_val DB_DATABASE)"; DB_NAME="${DB_NAME:-assets}"
# Dump as root: mysqldump's consistent-snapshot FLUSH needs RELOAD privilege,
# which the limited application user does not have.
DB_ROOT_PASS="$(env_val DB_ROOT_PASSWORD)"; DB_ROOT_PASS="${DB_ROOT_PASS:-rootsecret}"

command -v docker >/dev/null 2>&1 || { echo "docker not found" >&2; exit 1; }
docker compose ps db >/dev/null 2>&1 || { echo "compose service 'db' is not running" >&2; exit 1; }

mkdir -p "$BACKUP_DIR"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="${BACKUP_DIR}/${DB_NAME}-${STAMP}.sql.gz"

echo "Dumping '${DB_NAME}' -> ${OUT} ..."
docker compose exec -T -e MYSQL_PWD="${DB_ROOT_PASS}" db \
  mysqldump --single-transaction --quick --no-tablespaces \
  -uroot "${DB_NAME}" | gzip > "${OUT}"

echo "Backup written: ${OUT} ($(du -h "${OUT}" | cut -f1))"

# Prune old backups
find "${BACKUP_DIR}" -name '*.sql.gz' -type f -mtime +"${RETENTION_DAYS}" -print -delete \
  | sed 's/^/Pruned: /' || true

echo "Done. Retention: ${RETENTION_DAYS} days."
