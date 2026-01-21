#!/usr/bin/env bash
set -euo pipefail

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
# OS detection
#############################################
OS_ID=""
OS_LIKE=""
OS_FAMILY=""  # debian|rhel
detect_os(){
  if [[ -r /etc/os-release ]]; then
    # shellcheck disable=SC1091
    . /etc/os-release
    OS_ID="${ID:-}"
    OS_LIKE="${ID_LIKE:-}"
  fi

  if [[ "$OS_ID" =~ (ubuntu|debian) ]] || [[ "$OS_LIKE" =~ (debian|ubuntu) ]]; then
    OS_FAMILY="debian"
  elif [[ "$OS_ID" =~ (rhel|centos|rocky|almalinux|fedora) ]] || [[ "$OS_LIKE" =~ (rhel|fedora|centos) ]]; then
    OS_FAMILY="rhel"
  else
    die "Unsupported OS. Need Ubuntu/Debian or RHEL/Rocky/Alma."
  fi
}

#############################################
# Variables
#############################################
PROJECT_SLUG=""
DOMAIN=""
APP_NAME="i-portal"
APP_URL=""
APP_ENV="production"
APP_DEBUG="false"

APP_DIR=""
APP_USER=""

# Source code options
USE_GIT="no"
GIT_REPO=""
GIT_BRANCH="main"

# DB
DB_HOST="localhost"
DB_PORT="3306"
DB_NAME=""
DB_USER=""
DB_PASS=""

# MySQL admin connectivity
MYSQL_ADMIN_MODE="socket"    # socket|tcp
MYSQL_ROOT_USER="root"
MYSQL_ROOT_PASS=""           # only used for tcp with password

# Runtime drivers (IMPORTANT: default to FILE during install to avoid missing tables)
FINAL_CACHE_STORE="database"   # database|file (prompt at end)
FINAL_SESSION_DRIVER="database" # database|file (prompt at end)

# SSL
ENABLE_HTTPS="yes"
SSL_MODE="existing"          # existing|letsencrypt
CERT_FULLCHAIN=""
CERT_PRIVKEY=""
LE_EMAIL="admin@example.com"

# Services / paths
PHP_VER=""
PHP_FPM_SERVICE=""
PHP_FPM_SOCK=""
NGINX_SERVICE="nginx"
DB_SERVICE="mysql"           # mysql|mariadb

WEB_GROUP="www-data"         # debian: www-data, rhel: nginx

COMPOSER_BIN="/usr/local/bin/composer"
PHP_BIN="php"
NPM_BIN="npm"

#############################################
# Safety / discovery helpers
#############################################
list_existing_projects(){
  log "Checking /opt for existing Laravel projects..."
  if [[ -d /opt ]]; then
    local found="no"
    while IFS= read -r p; do
      found="yes"
      echo "  - $(basename "$(dirname "$p")")  ($(dirname "$p"))"
    done < <(find /opt -maxdepth 3 -type f -name artisan -print 2>/dev/null | sort || true)

    if [[ "$found" == "no" ]]; then
      echo "  (none found)"
    fi
  fi
}

nginx_site_paths(){
  if [[ "$OS_FAMILY" == "debian" ]]; then
    echo "/etc/nginx/sites-available/${PROJECT_SLUG}.conf" "/etc/nginx/sites-enabled/${PROJECT_SLUG}.conf"
  else
    echo "/etc/nginx/conf.d/${PROJECT_SLUG}.conf" ""
  fi
}

check_nginx_collision(){
  local avail enabled
  read -r avail enabled < <(nginx_site_paths)

  if [[ -f "$avail" ]]; then
    warn "Nginx vhost file already exists: $avail"
    if ! yesno "Overwrite this vhost file?" "n"; then
      die "Aborted."
    fi
  fi

  if [[ "$DOMAIN" != "_" ]]; then
    if nginx -T 2>/dev/null | grep -qE "server_name\s+.*\b${DOMAIN}\b"; then
      warn "Domain '${DOMAIN}' appears in existing Nginx config (could be another site)."
      if ! yesno "Continue anyway (may cause conflicts)?" "n"; then
        die "Aborted."
      fi
    fi
  fi
}

check_ports(){
  if command_exists ss; then
    if ss -lnt 2>/dev/null | awk '{print $4}' | grep -qE '(:|.)80$'; then
      log "Port 80 is in use (normal if Nginx is running)."
    fi
    if [[ "$ENABLE_HTTPS" == "yes" ]] && ss -lnt 2>/dev/null | awk '{print $4}' | grep -qE '(:|.)443$'; then
      log "Port 443 is in use (normal if Nginx is running)."
    fi
  fi
}

#############################################
# Install Composer (official)
#############################################
install_official_composer(){
  if [[ ! -x "$COMPOSER_BIN" ]]; then
    log "Installing official Composer -> ${COMPOSER_BIN}"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
  else
    log "Composer OK: $($COMPOSER_BIN --version | head -n1)"
  fi
}

detect_php_version(){
  if command_exists php; then
    PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
  else
    PHP_VER=""
  fi
}

#############################################
# Package installs (idempotent, multi-project safe)
#############################################
install_packages_debian(){
  log "Installing packages (Debian/Ubuntu)..."
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y
  apt-get install -y ca-certificates curl git unzip zip rsync gnupg lsb-release software-properties-common

  apt-get install -y nginx mysql-server

  if [[ "$OS_ID" == "ubuntu" ]]; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -y
  fi

  detect_php_version
  if [[ -z "$PHP_VER" ]]; then
    if apt-cache show php8.4-cli >/dev/null 2>&1; then PHP_VER="8.4"; else PHP_VER="8.2"; fi
  fi

  log "Using PHP ${PHP_VER}"
  apt-get install -y \
    "php${PHP_VER}" "php${PHP_VER}-cli" "php${PHP_VER}-fpm" \
    "php${PHP_VER}-mbstring" "php${PHP_VER}-xml" "php${PHP_VER}-curl" "php${PHP_VER}-zip" \
    "php${PHP_VER}-mysql" "php${PHP_VER}-bcmath" "php${PHP_VER}-intl" "php${PHP_VER}-gd"

  PHP_FPM_SERVICE="php${PHP_VER}-fpm"
  PHP_FPM_SOCK="/run/php/php${PHP_VER}-fpm.sock"
  WEB_GROUP="www-data"
  DB_SERVICE="mysql"

  if ! command_exists node; then
    log "Installing Node.js LTS..."
    curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
    apt-get install -y nodejs
  fi

  apt-get install -y certbot python3-certbot-nginx

  install_official_composer
}

install_packages_rhel(){
  log "Installing packages (RHEL/Rocky/Alma)..."
  dnf -y install ca-certificates curl git unzip zip tar rsync

  dnf -y install epel-release || true
  dnf -y install nginx

  dnf -y install mariadb-server mariadb
  DB_SERVICE="mariadb"

  dnf -y install php php-cli php-fpm php-mbstring php-xml php-curl php-zip php-mysqlnd php-bcmath php-intl php-gd

  PHP_FPM_SERVICE="php-fpm"
  PHP_FPM_SOCK="/run/php-fpm/www.sock"
  WEB_GROUP="nginx"

  dnf -y install nodejs npm || true
  dnf -y install certbot python3-certbot-nginx || true

  install_official_composer
}

enable_services(){
  log "Enabling and starting services..."
  systemctl enable --now "$NGINX_SERVICE" || true
  systemctl enable --now "$PHP_FPM_SERVICE" || true
  systemctl enable --now "$DB_SERVICE" || true
}

#############################################
# App user + directory (safe)
#############################################
create_app_user_and_dir(){
  APP_DIR="/opt/${PROJECT_SLUG}"
  APP_USER="${PROJECT_SLUG}"

  if [[ -d "$APP_DIR" ]]; then
    warn "Target directory exists: ${APP_DIR}"
    if ! yesno "Continue (may overwrite files in ${APP_DIR})?" "n"; then
      die "Aborted."
    fi
  fi

  if ! id -u "$APP_USER" >/dev/null 2>&1; then
    log "Creating Linux user: ${APP_USER}"
    useradd --system --create-home --shell /bin/bash "$APP_USER"
  else
    log "Linux user exists: ${APP_USER}"
  fi

  mkdir -p "$APP_DIR"
  chown -R "$APP_USER":"$APP_USER" "$APP_DIR"
}

#############################################
# Source code install (safe)
#############################################
run_as_app(){
  sudo -u "$APP_USER" bash -lc "$*"
}

copy_or_clone_code(){
  if [[ "$USE_GIT" == "yes" ]]; then
    log "Cloning repo into ${APP_DIR}..."
    if [[ -d "${APP_DIR}/.git" ]]; then
      warn "${APP_DIR} already looks like a git repo. Pulling latest instead."
      run_as_app "cd '$APP_DIR' && git fetch --all && git checkout '$GIT_BRANCH' && git pull --ff-only"
    else
      sudo -u "$APP_USER" bash -lc "git clone --branch '$GIT_BRANCH' '$GIT_REPO' '$APP_DIR'"
    fi
  else
    local SRC_DIR
    SRC_DIR="$(pwd)"
    [[ -f "${SRC_DIR}/artisan" ]] || die "Run install.sh from your Laravel project folder (artisan missing)."

    log "Copying project from ${SRC_DIR} -> ${APP_DIR}"
    rsync -a --delete --exclude ".git" "${SRC_DIR}/" "${APP_DIR}/"
    chown -R "$APP_USER":"$APP_USER" "$APP_DIR"
  fi
}

#############################################
# MySQL provisioning (socket-safe on Ubuntu)
#############################################
mysql_admin_exec() {
  local sql="$1"

  if [[ "$MYSQL_ADMIN_MODE" == "socket" ]]; then
    # Ubuntu mysql-server default: root uses auth_socket (works via local socket)
    mysql -u"$MYSQL_ROOT_USER" -e "$sql"
    return
  fi

  # TCP mode
  if [[ -n "$MYSQL_ROOT_PASS" ]]; then
    mysql -u"$MYSQL_ROOT_USER" -p"$MYSQL_ROOT_PASS" -h"$DB_HOST" -P"$DB_PORT" -e "$sql"
  else
    mysql -u"$MYSQL_ROOT_USER" -h"$DB_HOST" -P"$DB_PORT" -e "$sql"
  fi
}

detect_mysql_admin_mode(){
  # If provisioning remote DB host, must use tcp
  if [[ "$DB_HOST" != "localhost" ]] && [[ "$DB_HOST" != "127.0.0.1" ]]; then
    MYSQL_ADMIN_MODE="tcp"
    return
  fi
  # Prefer socket on Ubuntu
  MYSQL_ADMIN_MODE="socket"
}

check_db_collision(){
  local exists=""
  exists="$(mysql_admin_exec "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='${DB_NAME}';" 2>/dev/null | tail -n +2 || true)"
  if [[ -n "$exists" ]]; then
    warn "Database '${DB_NAME}' already exists."
    if ! yesno "Continue and reuse existing DB?" "n"; then
      die "Aborted."
    fi
  fi
}

setup_database(){
  log "Configuring database ${DB_NAME} and user ${DB_USER}..."
  systemctl restart "$DB_SERVICE" || true

  detect_mysql_admin_mode

  # sanity check admin access
  if ! mysql_admin_exec "SELECT 1;" >/dev/null 2>&1; then
    warn "Cannot access MySQL as root with current method (${MYSQL_ADMIN_MODE})."
    if yesno "Try TCP with a root password?" "y"; then
      MYSQL_ADMIN_MODE="tcp"
      prompt MYSQL_ROOT_PASS "MySQL root password"
      mysql_admin_exec "SELECT 1;" >/dev/null 2>&1 || die "MySQL root access failed (tcp)."
    else
      die "MySQL root access failed. Install cannot continue."
    fi
  fi

  check_db_collision

  mysql_admin_exec "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql_admin_exec "CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';"
  mysql_admin_exec "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';"
  mysql_admin_exec "FLUSH PRIVILEGES;"
}

db_app_test(){
  log "Testing app DB credentials..."
  mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "USE \`$DB_NAME\`; SELECT 1;" >/dev/null \
    || die "DB test failed for ${DB_USER}@${DB_HOST}:${DB_PORT}/${DB_NAME}"
}

#############################################
# .env creation (safe + uncomments)
#############################################
write_env_kv () {
  local file="$1" key="$2" value="$3"

  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"

  if grep -Eq "^[#[:space:]]*${key}=" "$file"; then
    sed -i -E "s|^[#[:space:]]*${key}=.*|${key}=\"${value}\"|g" "$file"
  else
    printf "\n%s=\"%s\"\n" "$key" "$value" >> "$file"
  fi
}

write_env(){
  log "Writing .env..."
  local envfile="${APP_DIR}/.env"

  [[ -f "${APP_DIR}/.env.example" ]] || die ".env.example not found"
  cp -f "${APP_DIR}/.env.example" "$envfile"
  chown "$APP_USER":"$APP_USER" "$envfile"
  chmod 640 "$envfile"

  write_env_kv "$envfile" "APP_NAME" "$APP_NAME"
  write_env_kv "$envfile" "APP_ENV" "$APP_ENV"
  write_env_kv "$envfile" "APP_DEBUG" "$APP_DEBUG"
  write_env_kv "$envfile" "APP_URL" "$APP_URL"

  write_env_kv "$envfile" "DB_CONNECTION" "mysql"
  write_env_kv "$envfile" "DB_HOST" "$DB_HOST"
  write_env_kv "$envfile" "DB_PORT" "$DB_PORT"
  write_env_kv "$envfile" "DB_DATABASE" "$DB_NAME"
  write_env_kv "$envfile" "DB_USERNAME" "$DB_USER"
  write_env_kv "$envfile" "DB_PASSWORD" "$DB_PASS"

  write_env_kv "$envfile" "FILESYSTEM_DISK" "local"

  # IMPORTANT: during install, avoid DB-backed cache/session before tables exist
  write_env_kv "$envfile" "CACHE_STORE" "file"
  write_env_kv "$envfile" "SESSION_DRIVER" "file"
  # avoid DB queue for first boot
  write_env_kv "$envfile" "QUEUE_CONNECTION" "sync"

  # hard check: ensure DB lines are present and not commented
  grep -Eq '^DB_HOST=' "$envfile" || die ".env DB_HOST not written"
  grep -Eq '^DB_DATABASE=' "$envfile" || die ".env DB_DATABASE not written"
  grep -Eq '^DB_USERNAME=' "$envfile" || die ".env DB_USERNAME not written"
  grep -Eq '^DB_PASSWORD=' "$envfile" || die ".env DB_PASSWORD not written"
}

#############################################
# Laravel install steps (FIXED ORDER + SAFE CACHE/SESSION)
#############################################
ensure_cache_session_migrations(){
  # create migrations only if missing
  local mdir="$APP_DIR/database/migrations"

  if [[ ! -d "$mdir" ]]; then
    warn "Migrations directory not found at $mdir (skipping cache/session migration creation)."
    return
  fi

  if ! ls "$mdir"/*cache_table*.php >/dev/null 2>&1; then
    log "Creating cache table migration..."
    run_as_app "cd '$APP_DIR' && $PHP_BIN artisan cache:table"
  else
    log "Cache table migration already exists."
  fi

  if ! ls "$mdir"/*sessions_table*.php >/dev/null 2>&1; then
    log "Creating sessions table migration..."
    run_as_app "cd '$APP_DIR' && $PHP_BIN artisan session:table"
  else
    log "Sessions table migration already exists."
  fi
}

finalize_env_after_migrate(){
  local envfile="${APP_DIR}/.env"

  if yesno "After install, use DATABASE for cache & sessions? (recommended for multi-server: No)" "y"; then
    FINAL_CACHE_STORE="database"
    FINAL_SESSION_DRIVER="database"
  else
    FINAL_CACHE_STORE="file"
    FINAL_SESSION_DRIVER="file"
  fi

  write_env_kv "$envfile" "CACHE_STORE" "$FINAL_CACHE_STORE"
  write_env_kv "$envfile" "SESSION_DRIVER" "$FINAL_SESSION_DRIVER"

  # If database chosen, ensure tables exist and migrate again (safe)
  if [[ "$FINAL_CACHE_STORE" == "database" ]] || [[ "$FINAL_SESSION_DRIVER" == "database" ]]; then
    log "Ensuring cache/session table migrations exist (for DATABASE mode)..."
    ensure_cache_session_migrations
    run_as_app "cd '$APP_DIR' && $PHP_BIN artisan migrate --force"
  fi

  log "Re-optimizing after final .env adjustments..."
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan config:clear || true"
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan cache:clear || true"
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan optimize"
}

laravel_install(){
  log "Preparing Laravel cache dirs..."
  run_as_app "cd '$APP_DIR' && mkdir -p storage bootstrap/cache"

  log "Removing any stale bootstrap cache files..."
  run_as_app "cd '$APP_DIR' && rm -f bootstrap/cache/config.php bootstrap/cache/services.php bootstrap/cache/packages.php"

  log "Installing PHP dependencies (composer) WITHOUT scripts (prevents DB cache errors during install)..."
  run_as_app "cd '$APP_DIR' && '$COMPOSER_BIN' install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts"

  log "Generating app key..."
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan key:generate --force"

  log "Running package discovery (safe: cache/session are file right now)..."
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan package:discover --ansi"

  log "Running migrations..."
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan migrate --force"

  log "Seeding PortalPermissionsSeeder (no tinker)..."
  if run_as_app "cd '$APP_DIR' && $PHP_BIN -r 'require \"vendor/autoload.php\"; echo class_exists(\"Database\\\\Seeders\\\\PortalPermissionsSeeder\")?\"1\":\"0\";'" | grep -q '^1$'; then
    run_as_app "cd '$APP_DIR' && $PHP_BIN artisan db:seed --class='Database\\Seeders\\PortalPermissionsSeeder' --force"
  else
    warn "PortalPermissionsSeeder not found. Skipping."
  fi

  log "Building frontend assets..."
  if [[ -f "$APP_DIR/package.json" ]]; then
    run_as_app "cd '$APP_DIR' && $NPM_BIN ci || $NPM_BIN install"
    run_as_app "cd '$APP_DIR' && $NPM_BIN run build"
  else
    warn "package.json not found; skipping npm build."
  fi

  log "Optimizing (config/routes/views)..."
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan optimize"

  # Switch cache/session to DB (optional) after migrations
  finalize_env_after_migrate
}

#############################################
# Nginx vhost + SSL (multi-project safe)
#############################################
write_nginx_vhost_http(){
  local avail enabled
  read -r avail enabled < <(nginx_site_paths)

  log "Writing Nginx vhost (HTTP) -> ${avail}"

  cat > "$avail" <<EOF
server {
    listen 80;
    server_name ${DOMAIN};

    root ${APP_DIR}/public;
    index index.php index.html;

    client_max_body_size 25m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_index index.php;
        fastcgi_pass unix:${PHP_FPM_SOCK};
    }
}
EOF

  if [[ "$OS_FAMILY" == "debian" ]]; then
    if [[ -n "$enabled" ]]; then
      ln -sf "$avail" "$enabled"
    fi
  fi

  nginx -t
  systemctl reload "$NGINX_SERVICE"
}

enable_ssl_existing_certs(){
  [[ -f "$CERT_FULLCHAIN" ]] || die "Fullchain not found: $CERT_FULLCHAIN"
  [[ -f "$CERT_PRIVKEY" ]] || die "Privkey not found: $CERT_PRIVKEY"

  local avail enabled
  read -r avail enabled < <(nginx_site_paths)

  log "Updating vhost to HTTPS (existing certs)..."

  cat > "$avail" <<EOF
server {
    listen 80;
    server_name ${DOMAIN};
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ${DOMAIN};

    ssl_certificate     ${CERT_FULLCHAIN};
    ssl_certificate_key ${CERT_PRIVKEY};

    root ${APP_DIR}/public;
    index index.php index.html;

    client_max_body_size 25m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_index index.php;
        fastcgi_pass unix:${PHP_FPM_SOCK};
    }
}
EOF

  if [[ "$OS_FAMILY" == "debian" ]]; then
    if [[ -n "$enabled" ]]; then
      ln -sf "$avail" "$enabled"
    fi
  fi

  nginx -t
  systemctl reload "$NGINX_SERVICE"
}

enable_ssl_letsencrypt(){
  if [[ "$DOMAIN" == "_" ]] || [[ "$DOMAIN" == "localhost" ]]; then
    die "Let’s Encrypt requires a real domain. Provide a valid DOMAIN."
  fi
  log "Requesting Let’s Encrypt cert via certbot..."
  certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$LE_EMAIL" --redirect
  nginx -t
  systemctl reload "$NGINX_SERVICE"
}

#############################################
# Permissions for storage
#############################################
fix_permissions(){
  log "Fixing storage permissions..."
  mkdir -p "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
  chown -R "$APP_USER":"$APP_USER" "$APP_DIR"
  chmod -R ug+rwX "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
  chgrp -R "$WEB_GROUP" "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" || true
}

#############################################
# Main
#############################################
main(){
  require_root
  detect_os

  log "=== Laravel Install (multi-project safe) ==="

  list_existing_projects

  prompt PROJECT_SLUG "Project slug (used for /opt/<slug> and Linux user)" "i-portal"
  prompt APP_NAME "App name (informational)" "i-portal"

  prompt DOMAIN "Domain (e.g. portal.example.com). Use '_' for IP-only/http" "_"

  if [[ "$DOMAIN" == "_" ]]; then
    APP_URL="http://127.0.0.1"
    ENABLE_HTTPS="no"
  else
    if yesno "Enable HTTPS?" "y"; then
      ENABLE_HTTPS="yes"
      APP_URL="https://${DOMAIN}"
    else
      ENABLE_HTTPS="no"
      APP_URL="http://${DOMAIN}"
    fi
  fi

  if yesno "Install from Git repo (clone)?" "n"; then
    USE_GIT="yes"
    prompt GIT_REPO "Git repo URL (ssh or https)"
    prompt GIT_BRANCH "Git branch" "main"
  else
    USE_GIT="no"
    log "Will copy code from CURRENT directory: $(pwd)"
  fi

  prompt DB_HOST "MySQL host" "localhost"
  prompt DB_PORT "MySQL port" "3306"
  prompt DB_NAME "Database name" "${PROJECT_SLUG}"
  prompt DB_USER "Database user" "${PROJECT_SLUG}"
  prompt DB_PASS "Database password (will be stored in .env)"

  # Only ask for root password if we end up needing TCP
  if yesno "Will you provision MySQL over TCP (remote host / require root password)?" "n"; then
    MYSQL_ADMIN_MODE="tcp"
    prompt MYSQL_ROOT_PASS "MySQL root password"
  else
    MYSQL_ADMIN_MODE="socket"
  fi

  if [[ "$ENABLE_HTTPS" == "yes" ]]; then
    if yesno "Use existing SSL cert files (instead of Let’s Encrypt)?" "y"; then
      SSL_MODE="existing"
      prompt CERT_FULLCHAIN "Fullchain path (pem)" "/etc/ssl/${PROJECT_SLUG}.fullchain.pem"
      prompt CERT_PRIVKEY "Privkey path (key)" "/etc/ssl/${PROJECT_SLUG}.privkey.pem"
    else
      SSL_MODE="letsencrypt"
      prompt LE_EMAIL "Let's Encrypt email" "admin@example.com"
    fi
  fi

  if [[ "$OS_FAMILY" == "debian" ]]; then
    install_packages_debian
  else
    install_packages_rhel
  fi

  enable_services
  check_ports

  create_app_user_and_dir
  copy_or_clone_code

  check_nginx_collision

  setup_database
  db_app_test

  write_env
  fix_permissions

  write_nginx_vhost_http

  if [[ "$ENABLE_HTTPS" == "yes" ]]; then
    if [[ "$SSL_MODE" == "existing" ]]; then
      enable_ssl_existing_certs
    else
      enable_ssl_letsencrypt
    fi
  fi

  laravel_install

  systemctl restart "$PHP_FPM_SERVICE" || true
  systemctl reload "$NGINX_SERVICE" || true

  log "DONE."
  echo "-------------------------------------------"
  echo "Project:      ${PROJECT_SLUG}"
  echo "Path:         /opt/${PROJECT_SLUG}"
  echo "Linux user:   ${PROJECT_SLUG}"
  echo "URL:          ${APP_URL}"
  echo "DB:           ${DB_NAME}  (user: ${DB_USER})"
  echo "PHP-FPM:      ${PHP_FPM_SERVICE}  (sock: ${PHP_FPM_SOCK})"
  local avail enabled
  read -r avail enabled < <(nginx_site_paths)
  echo "Nginx vhost:  ${avail}"
  echo "-------------------------------------------"
}

main "$@"
