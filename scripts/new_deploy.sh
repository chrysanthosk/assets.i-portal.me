#!/usr/bin/env bash
set -euo pipefail

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

detect_os(){
  local id like
  if [[ -r /etc/os-release ]]; then
    # shellcheck disable=SC1091
    . /etc/os-release
    id="${ID:-}"
    like="${ID_LIKE:-}"
  fi

  if [[ "${id:-}" =~ (ubuntu|debian) ]] || [[ "${like:-}" =~ (debian|ubuntu) ]]; then
    echo "debian"
  elif [[ "${id:-}" =~ (rhel|centos|rocky|almalinux|fedora) ]] || [[ "${like:-}" =~ (rhel|fedora|centos) ]]; then
    echo "rhel"
  else
    echo "unknown"
  fi
}

run_as_app(){
  local user="$1" dir="$2" cmd="$3"
  sudo -u "$user" bash -lc "cd '$dir' && $cmd"
}

main(){
  require_root

  local OS_FAMILY
  OS_FAMILY="$(detect_os)"

  local PROJECT_SLUG APP_DIR APP_USER BRANCH
  local PHP_FPM_SERVICE NGINX_SERVICE
  local COMPOSER_BIN="/usr/local/bin/composer"
  local PHP_BIN="php"
  local NPM_BIN="npm"
  local WEB_GROUP="www-data"

  prompt PROJECT_SLUG "Project slug (folder in /opt)" "i-portal"
  APP_DIR="/opt/${PROJECT_SLUG}"
  APP_USER="${PROJECT_SLUG}"

  [[ -d "$APP_DIR" ]] || die "Not found: ${APP_DIR}"
  [[ -f "${APP_DIR}/artisan" ]] || die "Not a Laravel app: ${APP_DIR} (artisan missing)"

  if ! id -u "$APP_USER" >/dev/null 2>&1; then
    # fallback to folder owner
    APP_USER="$(stat -c '%U' "$APP_DIR" 2>/dev/null || true)"
    [[ -n "$APP_USER" ]] || die "Could not determine APP_USER"
    warn "Linux user '${PROJECT_SLUG}' not found. Using folder owner '${APP_USER}'."
  fi

  prompt BRANCH "Git branch to deploy" "main"

  if [[ "$OS_FAMILY" == "debian" ]]; then
    # try to auto-detect php-fpm
    PHP_FPM_SERVICE="$(systemctl list-units --type=service --no-legend | awk '{print $1}' | grep -E '^php[0-9]+\.[0-9]+-fpm\.service$' | head -n1 || true)"
    PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.2-fpm.service}"
    PHP_FPM_SERVICE="${PHP_FPM_SERVICE%.service}"
    WEB_GROUP="www-data"
  else
    PHP_FPM_SERVICE="php-fpm"
    WEB_GROUP="nginx"
  fi
  NGINX_SERVICE="nginx"

  log "Deploying ${PROJECT_SLUG} as user ${APP_USER} from ${APP_DIR}"

  # Git update
  if [[ -d "${APP_DIR}/.git" ]]; then
    log "Git fetch + checkout + pull (${BRANCH})..."
    run_as_app "$APP_USER" "$APP_DIR" "git fetch --all --prune"
    run_as_app "$APP_USER" "$APP_DIR" "git checkout '$BRANCH'"
    run_as_app "$APP_USER" "$APP_DIR" "git pull --ff-only origin '$BRANCH'"
  else
    warn "No .git found. Skipping git pull. (You may be deploying by rsync/copy)"
  fi

  # Backend deps
  log "Composer install..."
  run_as_app "$APP_USER" "$APP_DIR" "'$COMPOSER_BIN' install --no-dev --prefer-dist --optimize-autoloader"

  # Frontend deps/build (if needed)
  if [[ -f "$APP_DIR/package.json" ]]; then
    log "NPM build..."
    run_as_app "$APP_USER" "$APP_DIR" "$NPM_BIN ci || $NPM_BIN install"
    run_as_app "$APP_USER" "$APP_DIR" "$NPM_BIN run build"
  else
    warn "package.json not found; skipping npm."
  fi

  # Migrate
  log "Migrations..."
  run_as_app "$APP_USER" "$APP_DIR" "$PHP_BIN artisan migrate --force"

  # Seed permissions automatically (no tinker)
  if run_as_app "$APP_USER" "$APP_DIR" "$PHP_BIN -r 'require \"vendor/autoload.php\"; echo class_exists(\"Database\\\\Seeders\\\\PortalPermissionsSeeder\")?\"1\":\"0\";'" | grep -q '^1$'; then
    log "Seeding PortalPermissionsSeeder..."
    run_as_app "$APP_USER" "$APP_DIR" "$PHP_BIN artisan db:seed --class='Database\\Seeders\\PortalPermissionsSeeder' --force"
  else
    warn "PortalPermissionsSeeder not found; skipping."
  fi

  # Caches
  log "Clearing + optimizing caches..."
  run_as_app "$APP_USER" "$APP_DIR" "$PHP_BIN artisan optimize:clear"
  run_as_app "$APP_USER" "$APP_DIR" "$PHP_BIN artisan optimize"

  # Permissions
  log "Fixing storage permissions..."
  mkdir -p "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
  chown -R "$APP_USER":"$APP_USER" "$APP_DIR"
  chmod -R ug+rwX "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
  chgrp -R "$WEB_GROUP" "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" || true

  log "Restarting services..."
  systemctl restart "$PHP_FPM_SERVICE" || true
  systemctl reload "$NGINX_SERVICE" || true

  log "DONE."
}

main "$@"
