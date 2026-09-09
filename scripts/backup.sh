#!/usr/bin/env bash
set -euo pipefail

#############################################
# backup.sh — full backup of the Dockerised stack
#
#   1) MySQL dump (compose service "db")        -> backups/<db>-<stamp>.sql.gz
#   2) Uploaded files (storage/app: deeds, contracts, statements) -> backups/storage-<stamp>.tar.gz
#   3) Prune both older than RETENTION_DAYS
#   4) Optional off-server copy of the new files (rsync/scp target)
#
#   ./scripts/backup.sh
#
# Env overrides (or set them in .env under the same names):
#   BACKUP_DIR=backups          where archives are written (relative to repo root)
#   RETENTION_DAYS=14           delete archives older than this
#   BACKUP_REMOTE=              optional rsync/scp destination, e.g. user@nas:/backups/assets
#   BACKUP_SKIP_STORAGE=0       set 1 to dump only the database
#
# Install the nightly cron with:  sudo ./scripts/backup.sh --install-cron [HH:MM]
#############################################

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO_ROOT="$(pwd)"

env_val() { { grep -E "^${1}=" .env 2>/dev/null | tail -1 | cut -d= -f2- | sed "s/^[\"']//; s/[\"']\$//"; } || true; }

BACKUP_DIR="${BACKUP_DIR:-$(env_val BACKUP_DIR)}"; BACKUP_DIR="${BACKUP_DIR:-backups}"
RETENTION_DAYS="${RETENTION_DAYS:-$(env_val RETENTION_DAYS)}"; RETENTION_DAYS="${RETENTION_DAYS:-14}"
BACKUP_REMOTE="${BACKUP_REMOTE:-$(env_val BACKUP_REMOTE)}"
BACKUP_SKIP_STORAGE="${BACKUP_SKIP_STORAGE:-$(env_val BACKUP_SKIP_STORAGE)}"; BACKUP_SKIP_STORAGE="${BACKUP_SKIP_STORAGE:-0}"

log() { echo "[$(date '+%F %T')] $*"; }

# --- cron installer ----------------------------------------------------------
if [[ "${1:-}" == "--install-cron" ]]; then
  [[ "${EUID:-$(id -u)}" -eq 0 ]] || { echo "Run as root: sudo $0 --install-cron [HH:MM]" >&2; exit 1; }
  at="${2:-02:30}"; hh="${at%%:*}"; mm="${at##*:}"
  [[ "$hh" =~ ^[0-9]{1,2}$ && "$mm" =~ ^[0-9]{2}$ && "${hh#0}" -le 23 && "${mm#0}" -le 59 ]] || { echo "Time must be HH:MM (00:00–23:59)" >&2; exit 1; }
  cat > /etc/cron.d/assets-backup <<CRON
# Nightly backup of assets.i-portal.me (DB + uploads). Log: ${REPO_ROOT}/${BACKUP_DIR}/backup.log
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
${mm#0} ${hh#0} * * * root cd ${REPO_ROOT} && ./scripts/backup.sh >> ${REPO_ROOT}/${BACKUP_DIR}/backup.log 2>&1
CRON
  chmod 644 /etc/cron.d/assets-backup
  mkdir -p "${BACKUP_DIR}"
  log "Installed /etc/cron.d/assets-backup — runs daily at ${at} as root."
  exit 0
fi

# --- preflight ---------------------------------------------------------------
command -v docker >/dev/null 2>&1 || { echo "docker not found" >&2; exit 1; }
[[ -n "$(docker compose ps -q db 2>/dev/null)" ]] || { echo "compose service 'db' is not running" >&2; exit 1; }

DB_NAME="$(env_val DB_DATABASE)"; DB_NAME="${DB_NAME:-assets}"
# Dump as root: the consistent-snapshot FLUSH needs RELOAD, which the app user lacks.
DB_ROOT_PASS="$(env_val DB_ROOT_PASSWORD)"; DB_ROOT_PASS="${DB_ROOT_PASS:-rootsecret}"

mkdir -p "$BACKUP_DIR"
STAMP="$(date +%Y%m%d-%H%M%S)"
NEW_FILES=()

# --- 1) database -------------------------------------------------------------
DB_OUT="${BACKUP_DIR}/${DB_NAME}-${STAMP}.sql.gz"
log "Dumping database '${DB_NAME}' -> ${DB_OUT}"
docker compose exec -T -e MYSQL_PWD="${DB_ROOT_PASS}" db \
  mysqldump --single-transaction --quick --no-tablespaces --routines --triggers --set-gtid-purged=OFF \
  -uroot "${DB_NAME}" | gzip > "${DB_OUT}"
gzip -t "${DB_OUT}"
NEW_FILES+=("${DB_OUT}")
log "  $(du -h "${DB_OUT}" | cut -f1)"

# --- 2) storage volume (uploads: title deeds, documents) ---------------------
if [[ "${BACKUP_SKIP_STORAGE}" != "1" ]]; then
  ST_OUT="${BACKUP_DIR}/storage-${STAMP}.tar.gz"
  log "Archiving storage/app -> ${ST_OUT}"
  # tar inside the app container so we read the named volume directly
  docker compose exec -T app sh -c 'cd /var/www/html/storage && tar -czf - app' > "${ST_OUT}"
  gzip -t "${ST_OUT}"
  NEW_FILES+=("${ST_OUT}")
  log "  $(du -h "${ST_OUT}" | cut -f1)"
fi

# --- 3) prune ----------------------------------------------------------------
find "${BACKUP_DIR}" \( -name "${DB_NAME}-*.sql.gz" -o -name 'storage-*.tar.gz' \) -type f -mtime +"${RETENTION_DAYS}" -print -delete \
  | sed 's/^/Pruned: /' || true

# --- 4) off-server copy --------------------------------------------------------
if [[ -n "${BACKUP_REMOTE}" ]]; then
  if command -v rsync >/dev/null 2>&1; then
    log "Copying to ${BACKUP_REMOTE} (rsync)"
    rsync -a --partial "${NEW_FILES[@]}" "${BACKUP_REMOTE}/"
  else
    log "Copying to ${BACKUP_REMOTE} (scp)"
    scp -q "${NEW_FILES[@]}" "${BACKUP_REMOTE}/"
  fi
fi

log "Done. Kept ${RETENTION_DAYS} days in ${BACKUP_DIR}."
