#!/usr/bin/env bash
set -euo pipefail

#############################################
# new_deploy.sh
# - Updates existing /opt/<project> deployment safely
# - Supports: git pull OR rsync copy
# - Runs composer, migrations, cache, npm build (optional)
# - Does NOT touch MySQL users/DB grants unless you ask it to
#############################################

#############################################
# Helpers
#############################################
log()  { echo -e "\n\033[1;32m[INFO]\033[0m $*"; }
warn() { echo -e "\n\033[1;33m[WARN]\033[0m $*"; }
err()  { echo -e "\n\033[1;31m[ERR ]\033[0m $*" >&2; }
die()  { err "$*"; exit 1; }

require_root(){ [[ "${EUID:-$(id -u)}" -eq 0 ]] || die "Run as root: sudo $0"; }

prompt() { # var, msg, default(optional)
  local var="$1" msg="$2" def="${3:-}" val=""
  if [[ -n "$def" ]]; then
    read -r -p "$msg [$def]: " val
    val="${val:-$def}"
  else
    read -r -p "$msg: " val
  fi
  printf -v "$var" "%s" "$val"
}

yesno(){ # msg default(y/n)
  local msg="$1" def="${2:-y}" ans=""
  read -r -p "$msg [${def}]: " ans
  ans="${ans:-$def}"
  [[ "$ans" =~ ^[Yy]$ ]]
}

command_exists(){ command -v "$1" >/dev/null 2>&1; }

#############################################
# Safe token helper (same idea as install.sh)
#############################################
to_safe_token(){
  local s="$1"
  s="$(echo "$s" | tr '[:upper:]' '[:lower:]')"
  s="$(echo "$s" | sed -E 's/[^a-z0-9_]+/_/g; s/^_+//; s/_+$//; s/__+/_/g')"
  [[ -n "$s" ]] || s="app"
  echo "$s"
}

#############################################
# OS detection (for nginx/php-fpm service names)
#############################################
OS_FAMILY="debian"
detect_os_family(){
  if [[ -r /etc/os-release ]]; then
    # shellcheck disable=SC1091
    . /etc/os-release
    if [[ "${ID:-}" =~ (ubuntu|debian) ]] || [[ "${ID_LIKE:-}" =~ (debian|ubuntu) ]]; then
      OS_FAMILY="debian"
    else
      OS_FAMILY="rhel"
    fi
  fi
}

#############################################
# Defaults / Variables
#############################################
PROJECT_SLUG_RAW=""
PROJECT_SAFE=""
APP_DIR=""
APP_USER=""

DEPLOY_MODE="auto"         # auto|git|copy
SOURCE_PATH=""             # for copy mode
GIT_BRANCH="main"          # for git mode (optional checkout)

COMPOSER_BIN="/usr/local/bin/composer"
PHP_BIN="php"
NPM_BIN="npm"

# behaviors
RUN_NPM="auto"             # auto|yes|no
RUN_MIGRATE="yes"
RUN_SEED="no"              # optional
SEED_CLASS="Database\\Seeders\\PortalPermissionsSeeder"
OPTIMIZE="yes"
RESTART_PHPFPM="yes"
RELOAD_NGINX="yes"

# lock file
LOCK_FILE=""

#############################################
# Detect PHP-FPM service + socket (best effort)
#############################################
PHP_FPM_SERVICE="php-fpm"
NGINX_SERVICE="nginx"

detect_php_fpm_service(){
  # Debian/Ubuntu commonly: php8.x-fpm
  if [[ "$OS_FAMILY" == "debian" ]]; then
    local svc
    svc="$(systemctl list-unit-files --type=service 2>/dev/null | awk '{print $1}' | grep -E '^php[0-9]+\.[0-9]+-fpm\.service$' | sort -V | tail -n1 || true)"
    if [[ -n "$svc" ]]; then
      PHP_FPM_SERVICE="${svc%.service}"
    else
      PHP_FPM_SERVICE="php-fpm"
    fi
  else
    PHP_FPM_SERVICE="php-fpm"
  fi
}

#############################################
# Lock (avoid concurrent deploys)
#############################################
acquire_lock(){
  LOCK_FILE="/tmp/deploy_${PROJECT_SAFE}.lock"
  exec 9>"$LOCK_FILE"
  if ! flock -n 9; then
    die "Another deploy appears to be running (lock: $LOCK_FILE)."
  fi
}

#############################################
# Run as app user
#############################################
run_as_app(){
  sudo -u "$APP_USER" bash -lc "$*"
}

#############################################
# Determine deploy mode
#############################################
resolve_default_source_path(){
  local script_dir
  script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  echo "$script_dir"
}

detect_deploy_mode(){
  if [[ "$DEPLOY_MODE" != "auto" ]]; then
    return
  fi

  if [[ -d "${APP_DIR}/.git" ]]; then
    DEPLOY_MODE="git"
  else
    DEPLOY_MODE="copy"
  fi
}

#############################################
# Pre-flight checks
#############################################
preflight(){
  [[ -d "$APP_DIR" ]] || die "App directory not found: $APP_DIR"
  [[ -f "${APP_DIR}/artisan" ]] || die "Not a Laravel app (artisan missing): $APP_DIR"
  [[ -f "${APP_DIR}/.env" ]] || die ".env not found in ${APP_DIR} (install first)."

  if [[ ! -x "$COMPOSER_BIN" ]]; then
    if command_exists composer; then
      COMPOSER_BIN="$(command -v composer)"
    else
      die "Composer not found. Install composer first."
    fi
  fi

  if ! command_exists "$PHP_BIN"; then
    die "PHP not found."
  fi
}

#############################################
# Code update
#############################################
update_code_git(){
  log "Updating code via git..."
  run_as_app "cd '$APP_DIR' && git fetch --all"
  # keep it simple: checkout branch then pull ff-only
  run_as_app "cd '$APP_DIR' && git checkout '$GIT_BRANCH' >/dev/null 2>&1 || true"
  run_as_app "cd '$APP_DIR' && git pull --ff-only"
}

update_code_copy(){
  [[ -n "$SOURCE_PATH" ]] || SOURCE_PATH="$(resolve_default_source_path)"
  SOURCE_PATH="$(cd "$SOURCE_PATH" && pwd)"

  [[ -f "${SOURCE_PATH}/artisan" ]] || die "Source path is not a Laravel project (artisan missing): ${SOURCE_PATH}"

  log "Updating code via rsync from ${SOURCE_PATH} -> ${APP_DIR} ..."
  # keep .env on server, never overwrite it
  rsync -a --delete \
    --exclude ".git" \
    --exclude ".env" \
    --exclude "storage/***" \
    "${SOURCE_PATH}/" "${APP_DIR}/"

  chown -R "$APP_USER":"$APP_USER" "$APP_DIR"
}

#############################################
# Laravel steps
#############################################
laravel_steps(){
  log "Putting app into maintenance mode..."
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan down --render='errors::503' >/dev/null 2>&1 || $PHP_BIN artisan down || true"

  log "Composer install (no-dev, optimized)..."
  # Use scripts during deploy; DB and tables should exist already
  run_as_app "cd '$APP_DIR' && '$COMPOSER_BIN' install --no-dev --prefer-dist --optimize-autoloader --no-interaction"

  if [[ "$RUN_NPM" == "auto" ]]; then
    if [[ -f "$APP_DIR/package.json" ]]; then
      RUN_NPM="yes"
    else
      RUN_NPM="no"
    fi
  fi

  if [[ "$RUN_NPM" == "yes" ]]; then
    log "Building frontend assets..."
    run_as_app "cd '$APP_DIR' && $NPM_BIN ci || $NPM_BIN install"
    run_as_app "cd '$APP_DIR' && $NPM_BIN run build"
  else
    log "Skipping frontend build (no package.json or disabled)."
  fi

  log "Clearing caches..."
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan config:clear || true"
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan cache:clear || true"
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan route:clear || true"
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan view:clear || true"

  if [[ "$RUN_MIGRATE" == "yes" ]]; then
    log "Running migrations..."
    run_as_app "cd '$APP_DIR' && $PHP_BIN artisan migrate --force"
  else
    log "Skipping migrations."
  fi

  if [[ "$RUN_SEED" == "yes" ]]; then
    log "Running seeder: ${SEED_CLASS}"
    run_as_app "cd '$APP_DIR' && $PHP_BIN artisan db:seed --class='${SEED_CLASS}' --force"
  fi

  if [[ "$OPTIMIZE" == "yes" ]]; then
    log "Optimizing..."
    run_as_app "cd '$APP_DIR' && $PHP_BIN artisan optimize"
  fi

  log "Bringing app back up..."
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan up || true"
}

#############################################
# Restart services
#############################################
restart_services(){
  detect_php_fpm_service

  if [[ "$RESTART_PHPFPM" == "yes" ]]; then
    log "Restarting PHP-FPM (${PHP_FPM_SERVICE})..."
    systemctl restart "$PHP_FPM_SERVICE" >/dev/null 2>&1 || warn "Could not restart ${PHP_FPM_SERVICE} (check service name)."
  fi

  if [[ "$RELOAD_NGINX" == "yes" ]]; then
    log "Reloading Nginx..."
    nginx -t >/dev/null 2>&1 || warn "nginx -t failed (check configs)."
    systemctl reload "$NGINX_SERVICE" >/dev/null 2>&1 || warn "Could not reload nginx."
  fi
}

#############################################
# Main
#############################################
main(){
  require_root
  detect_os_family

  log "=== Laravel Deploy (new_deploy.sh) ==="

  prompt PROJECT_SLUG_RAW "Project slug (folder under /opt)" "assets.i-portal.me"
  PROJECT_SAFE="$(to_safe_token "$PROJECT_SLUG_RAW")"
  APP_DIR="/opt/${PROJECT_SLUG_RAW}"
  APP_USER="${PROJECT_SAFE}"

  acquire_lock

  if ! id -u "$APP_USER" >/dev/null 2>&1; then
    warn "Linux user not found: ${APP_USER}. Using root to run steps (not recommended)."
    APP_USER="root"
  fi

  preflight

  prompt DEPLOY_MODE "Deploy mode (auto|git|copy)" "auto"
  detect_deploy_mode

  if [[ "$DEPLOY_MODE" == "git" ]]; then
    prompt GIT_BRANCH "Git branch to deploy" "$GIT_BRANCH"
  elif [[ "$DEPLOY_MODE" == "copy" ]]; then
    local default_src
    default_src="$(resolve_default_source_path)"
    prompt SOURCE_PATH "Local source path (Laravel project folder)" "$default_src"
  else
    die "Invalid deploy mode: $DEPLOY_MODE"
  fi

  # Optional toggles
  if ! yesno "Run migrations?" "y"; then RUN_MIGRATE="no"; fi
  if yesno "Run PortalPermissionsSeeder?" "n"; then RUN_SEED="yes"; fi
  if ! yesno "Run optimize (cache config/routes/views)?" "y"; then OPTIMIZE="no"; fi
  if ! yesno "Restart PHP-FPM?" "y"; then RESTART_PHPFPM="no"; fi
  if ! yesno "Reload Nginx?" "y"; then RELOAD_NGINX="no"; fi

  log "Deploy target: ${APP_DIR} (user: ${APP_USER})"
  log "Mode: ${DEPLOY_MODE}"

  if [[ "$DEPLOY_MODE" == "git" ]]; then
    update_code_git
  else
    update_code_copy
  fi

  laravel_steps
  restart_services

  log "DEPLOY DONE."
  echo "-------------------------------------------"
  echo "Project:     ${PROJECT_SLUG_RAW}"
  echo "Path:        ${APP_DIR}"
  echo "Mode:        ${DEPLOY_MODE}"
  echo "Migrations:  ${RUN_MIGRATE}"
  echo "Seeder:      ${RUN_SEED}"
  echo "Optimize:    ${OPTIMIZE}"
  echo "-------------------------------------------"
}

main "$@"
