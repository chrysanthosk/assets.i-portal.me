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

env_escape() {
  local s="$1"
  s="${s//\\/\\\\}"
  s="${s//\"/\\\"}"
  s="${s//$/\\$}"
  s="${s//$'\n'/\\n}"
  printf "%s" "$s"
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
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME=""
DB_USER=""
DB_PASS=""

MYSQL_ROOT_USER="root"
MYSQL_ROOT_PASS=""   # optional

# SSL
ENABLE_HTTPS="yes"
SSL_MODE="existing"  # existing|letsencrypt
CERT_FULLCHAIN=""
CERT_PRIVKEY=""
LE_EMAIL="admin@example.com"

# Services / paths
PHP_VER=""
PHP_FPM_SERVICE=""
PHP_FPM_SOCK=""
NGINX_SERVICE="nginx"
DB_SERVICE="mysql"     # mysql|mariadb

WEB_GROUP="www-data"   # debian: www-data, rhel: nginx

COMPOSER_BIN="/usr/local/bin/composer"
PHP_BIN="php"
NODE_BIN="node"
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

  # Domain already referenced anywhere in Nginx config?
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
      log "Port 80 is in use (this is normal if Nginx is running)."
    fi
    if [[ "$ENABLE_HTTPS" == "yes" ]] && ss -lnt 2>/dev/null | awk '{print $4}' | grep -qE '(:|.)443$'; then
      log "Port 443 is in use (this is normal if Nginx is running)."
    fi
  fi
}

#############################################
# Install Composer (official)
#############################################
install_official_composer(){
  local need="no"
  if [[ ! -x "$COMPOSER_BIN" ]]; then need="yes"; fi
  if command -v composer >/dev/null 2>&1; then
    local p
    p="$(command -v composer)"
    if [[ "$p" == "/usr/bin/composer" ]] || [[ "$p" == "/usr/share/php/composer" ]] || [[ "$p" == "/usr/share/php/Composer" ]]; then
      need="yes"
    fi
  else
    need="yes"
  fi

  if [[ "$need" == "yes" ]]; then
    log "Installing official Composer -> ${COMPOSER_BIN}"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
  else
    log "Composer OK: $($COMPOSER_BIN --version | head -n1)"
  fi
}

detect_php_version(){
  if command -v php >/dev/null 2>&1; then
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

  # Nginx + MySQL
  apt-get install -y nginx mysql-server

  # PHP
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

  # Node (LTS)
  if ! command -v node >/dev/null 2>&1; then
    log "Installing Node.js LTS..."
    curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
    apt-get install -y nodejs
  fi

  # Certbot
  apt-get install -y certbot python3-certbot-nginx

  install_official_composer
}

install_packages_rhel(){
  log "Installing packages (RHEL/Rocky/Alma)..."
  dnf -y install ca-certificates curl git unzip zip tar rsync

  dnf -y install epel-release || true
  dnf -y install nginx

  # MariaDB
  dnf -y install mariadb-server mariadb
  DB_SERVICE="mariadb"

  # PHP
  dnf -y install php php-cli php-fpm php-mbstring php-xml php-curl php-zip php-mysqlnd php-bcmath php-intl php-gd

  PHP_FPM_SERVICE="php-fpm"
  PHP_FPM_SOCK="/run/php-fpm/www.sock"
  WEB_GROUP="nginx"

  # Node
  dnf -y install nodejs npm || true

  # Certbot
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
    [[ -f "${SRC_DIR}/artisan" ]] || die "Current directory does not look like Laravel project (artisan missing). Run install.sh from your project folder."
    log "Copying project from ${SRC_DIR} -> ${APP_DIR}"

    if [[ -d "$APP_DIR" ]] && [[ -f "$APP_DIR/artisan" ]]; then
      warn "${APP_DIR} already has a Laravel project."
      if ! yesno "Overwrite ${APP_DIR} with current folder (rsync --delete)?" "n"; then
        die "Aborted."
      fi
    fi

    rsync -a --delete --exclude ".git" "${SRC_DIR}/" "${APP_DIR}/"
    chown -R "$APP_USER":"$APP_USER" "$APP_DIR"
  fi
}

#############################################
# MySQL provisioning (safe)
#############################################
mysql_exec() {
  local sql="$1"
  if [[ -n "$MYSQL_ROOT_PASS" ]]; then
    mysql -u"$MYSQL_ROOT_USER" -p"$MYSQL_ROOT_PASS" -h"$DB_HOST" -P"$DB_PORT" -e "$sql"
  else
    mysql -u"$MYSQL_ROOT_USER" -h"$DB_HOST" -P"$DB_PORT" -e "$sql"
  fi
}

check_db_collision(){
  local exists
  exists="$(mysql_exec "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='${DB_NAME}';" 2>/dev/null | tail -n +2 || true)"
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

  # collision prompt
  check_db_collision

  mysql_exec "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql_exec "CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';"
  mysql_exec "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';"
  mysql_exec "FLUSH PRIVILEGES;"
}

#############################################
# .env creation (safe)
#############################################
write_env(){
  log "Writing .env..."
  local envfile="${APP_DIR}/.env"

  [[ -f "${APP_DIR}/.env.example" ]] || die ".env.example not found in ${APP_DIR}"

  if [[ -f "$envfile" ]]; then
    warn ".env already exists at ${envfile}"
    if yesno "Backup existing .env to .env.bak?" "y"; then
      cp -f "$envfile" "${envfile}.bak.$(date +%Y%m%d%H%M%S)"
    fi
    if ! yesno "Overwrite .env now?" "n"; then
      die "Aborted."
    fi
  fi

  sudo -u "$APP_USER" bash -lc "cp -f '${APP_DIR}/.env.example' '${envfile}'"

  sudo -u "$APP_USER" bash -lc "perl -0777 -i -pe 's/^APP_NAME=.*/APP_NAME=\"$(env_escape "$APP_NAME")\"/m; s/^APP_ENV=.*/APP_ENV=${APP_ENV}/m; s/^APP_DEBUG=.*/APP_DEBUG=${APP_DEBUG}/m; s|^APP_URL=.*|APP_URL=${APP_URL}|m;' '${envfile}'"

  sudo -u "$APP_USER" bash -lc "perl -0777 -i -pe 's/^DB_CONNECTION=.*/DB_CONNECTION=mysql/m; s/^DB_HOST=.*/DB_HOST=${DB_HOST}/m; s/^DB_PORT=.*/DB_PORT=${DB_PORT}/m; s/^DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/m; s/^DB_USERNAME=.*/DB_USERNAME=${DB_USER}/m; s/^DB_PASSWORD=.*/DB_PASSWORD=\"$(env_escape "$DB_PASS")\"/m;' '${envfile}'"

  if grep -q '^FILESYSTEM_DISK=' "$envfile"; then
    sudo -u "$APP_USER" bash -lc "perl -0777 -i -pe 's/^FILESYSTEM_DISK=.*/FILESYSTEM_DISK=local/m;' '${envfile}'"
  else
    echo "FILESYSTEM_DISK=local" >> "$envfile"
  fi

  chown "$APP_USER":"$APP_USER" "$envfile"
  chmod 640 "$envfile"
}

#############################################
# Laravel install steps
#############################################
run_as_app(){
  sudo -u "$APP_USER" bash -lc "$*"
}

laravel_install(){
  log "Installing PHP dependencies (composer)..."
  run_as_app "cd '$APP_DIR' && '$COMPOSER_BIN' install --no-dev --prefer-dist --optimize-autoloader"

  log "Generating app key..."
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan key:generate --force"

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

  log "Caching config/routes/views..."
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan optimize"
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

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff2?)\$ {
        expires 7d;
        add_header Cache-Control "public";
        try_files \$uri =404;
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

    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:10m;
    ssl_session_tickets off;

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

  # DB inputs
  prompt DB_HOST "MySQL host" "127.0.0.1"
  prompt DB_PORT "MySQL port" "3306"
  prompt DB_NAME "Database name" "${PROJECT_SLUG}"
  prompt DB_USER "Database user" "${PROJECT_SLUG}"
  prompt DB_PASS "Database password (will be stored in .env)"

  if yesno "Does MySQL root require a password?" "n"; then
    prompt MYSQL_ROOT_PASS "MySQL root password"
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

  # Install packages
  if [[ "$OS_FAMILY" == "debian" ]]; then
    install_packages_debian
  else
    install_packages_rhel
  fi

  enable_services

  check_ports

  # Create user + /opt dir
  create_app_user_and_dir

  # Copy/clone code
  copy_or_clone_code

  # Nginx safety checks (after slug/domain known, after nginx installed)
  check_nginx_collision

  # DB
  setup_database

  # .env
  write_env

  # Permissions
  fix_permissions

  # Nginx HTTP vhost first (needed for LE)
  write_nginx_vhost_http

  # SSL
  if [[ "$ENABLE_HTTPS" == "yes" ]]; then
    if [[ "$SSL_MODE" == "existing" ]]; then
      enable_ssl_existing_certs
    else
      enable_ssl_letsencrypt
    fi
  fi

  # Laravel install steps
  laravel_install

  systemctl restart "$PHP_FPM_SERVICE" || true
  systemctl reload "$NGINX_SERVICE" || true

  log "DONE."
  echo "-------------------------------------------"
  echo "Project:      ${PROJECT_SLUG}"
  echo "Path:         ${APP_DIR}"
  echo "Linux user:   ${APP_USER}"
  echo "URL:          ${APP_URL}"
  echo "DB:           ${DB_NAME}  (user: ${DB_USER})"
  echo "PHP-FPM:      ${PHP_FPM_SERVICE}  (sock: ${PHP_FPM_SOCK})"
  local avail enabled
  read -r avail enabled < <(nginx_site_paths)
  echo "Nginx vhost:  ${avail}"
  echo "-------------------------------------------"
}

main "$@"
