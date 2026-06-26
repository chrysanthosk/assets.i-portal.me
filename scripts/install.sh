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

prompt_secret() { # var, msg
  local var="$1" msg="$2" val=""
  read -r -s -p "$msg: " val
  echo
  printf -v "$var" "%s" "$val"
}

yesno(){ # msg default(y/n)
  local msg="$1" def="${2:-y}" ans=""
  read -r -p "$msg [${def}]: " ans
  ans="${ans:-$def}"
  [[ "$ans" =~ ^[Yy]$ ]]
}

command_exists(){ command -v "$1" >/dev/null 2>&1; }

ensure_min_len() { # value, min, msg
  local v="$1" min="$2" msg="$3"
  if [[ "${#v}" -lt "$min" ]]; then
    die "$msg"
  fi
}

# Convert to safe token for DB/user/nginx filenames:
# - allow: a-z0-9_
# - everything else -> _
to_safe_token(){
  local s="$1"
  s="$(echo "$s" | tr '[:upper:]' '[:lower:]')"
  s="$(echo "$s" | sed -E 's/[^a-z0-9_]+/_/g; s/^_+//; s/_+$//; s/__+/_/g')"
  [[ -n "$s" ]] || s="app"
  echo "$s"
}

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
PROJECT_SLUG_RAW=""
PROJECT_SAFE=""        # safe token for DB/user/nginx conf
DOMAIN=""
APP_NAME="assets.i-portal.me"
APP_URL=""
APP_ENV="production"
APP_DEBUG="false"

APP_DIR=""             # /opt/<PROJECT_SLUG_RAW>
APP_USER=""            # safe system user (no dots)
SOURCE_MODE="copy"     # copy|git
SOURCE_PATH=""         # where project code lives when copy mode
GIT_REPO=""
GIT_BRANCH="main"

# DB (MySQL)
DB_HOST="localhost"
DB_PORT="3306"
DB_NAME_RAW=""         # user may type, but we sanitize to DB_NAME_SAFE
DB_NAME_SAFE=""
DB_USER_SAFE=""
DB_PASS=""

# MySQL admin connectivity
MYSQL_ADMIN_MODE="socket"   # socket|tcp
MYSQL_ROOT_USER="root"
MYSQL_ROOT_PASS=""          # only for tcp

# SSL
ENABLE_HTTPS="yes"
SSL_MODE="existing"         # existing|letsencrypt
CERT_FULLCHAIN=""
CERT_PRIVKEY=""
LE_EMAIL="admin@example.com"

# Admin user (Laravel make:admin)
ADMIN_NAME=""
ADMIN_EMAIL=""
ADMIN_USERNAME=""
ADMIN_PASS=""

# Services / paths
PHP_VER=""
PHP_FPM_SERVICE=""
PHP_FPM_SOCK=""
NGINX_SERVICE="nginx"
DB_SERVICE="mysql"          # mysql|mariadb
WEB_GROUP="www-data"        # debian: www-data, rhel: nginx

COMPOSER_BIN="/usr/local/bin/composer"
PHP_BIN="php"
NPM_BIN="npm"

#############################################
# Discovery helpers
#############################################
list_existing_projects(){
  log "Existing Laravel projects under /opt (if any):"
  if [[ -d /opt ]]; then
    local found="no"
    while IFS= read -r p; do
      found="yes"
      echo "  - $(dirname "$p")"
    done < <(find /opt -maxdepth 3 -type f -name artisan -print 2>/dev/null | sort || true)

    [[ "$found" == "no" ]] && echo "  (none found)"
  fi
}

nginx_site_paths(){
  if [[ "$OS_FAMILY" == "debian" ]]; then
    echo "/etc/nginx/sites-available/${PROJECT_SAFE}.conf" "/etc/nginx/sites-enabled/${PROJECT_SAFE}.conf"
  else
    echo "/etc/nginx/conf.d/${PROJECT_SAFE}.conf" ""
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
# Package installs (idempotent)
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
# App user + directory
#############################################
create_app_user_and_dir(){
  APP_DIR="/opt/${PROJECT_SLUG_RAW}"
  APP_USER="${PROJECT_SAFE}"

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
# Source code install (RUN FROM ANYWHERE)
#############################################
run_as_app(){
  sudo -u "$APP_USER" bash -lc "$*"
}

resolve_default_source_path(){
  # default to the folder where install.sh lives (works even if you run it from elsewhere)
  local script_dir
  script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  echo "$script_dir"
}

copy_or_clone_code(){
  if [[ "$SOURCE_MODE" == "git" ]]; then
    log "Deploying code via Git into ${APP_DIR}..."
    if [[ -d "${APP_DIR}/.git" ]]; then
      warn "Repo already exists at ${APP_DIR}. Pulling latest instead."
      run_as_app "cd '$APP_DIR' && git fetch --all && git checkout '$GIT_BRANCH' && git pull --ff-only"
    else
      sudo -u "$APP_USER" bash -lc "git clone --branch '$GIT_BRANCH' '$GIT_REPO' '$APP_DIR'"
    fi
    return
  fi

  # Copy mode
  [[ -n "$SOURCE_PATH" ]] || SOURCE_PATH="$(resolve_default_source_path)"
  SOURCE_PATH="$(cd "$SOURCE_PATH" && pwd)"

  if [[ ! -f "${SOURCE_PATH}/artisan" ]]; then
    die "Source path does not look like a Laravel project (artisan missing): ${SOURCE_PATH}"
  fi

  log "Copying project from ${SOURCE_PATH} -> ${APP_DIR}"
  rsync -a --delete --exclude ".git" "${SOURCE_PATH}/" "${APP_DIR}/"
  chown -R "$APP_USER":"$APP_USER" "$APP_DIR"
}

#############################################
# MySQL provisioning (socket-safe on Ubuntu)
#############################################
mysql_admin_exec() {
  local sql="$1"

  if [[ "$MYSQL_ADMIN_MODE" == "socket" ]]; then
    # On Ubuntu mysql-server, root commonly uses auth_socket (no -h)
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
  # remote host => tcp
  if [[ "$DB_HOST" != "localhost" ]] && [[ "$DB_HOST" != "127.0.0.1" ]]; then
    MYSQL_ADMIN_MODE="tcp"
  else
    MYSQL_ADMIN_MODE="socket"
  fi
}

setup_database(){
  log "Configuring database + user..."

  systemctl restart "$DB_SERVICE" || true
  detect_mysql_admin_mode

  # Admin connectivity test
  if ! mysql_admin_exec "SELECT 1;" >/dev/null 2>&1; then
    warn "Cannot access MySQL as root with mode: ${MYSQL_ADMIN_MODE}"
    if yesno "Try TCP with root password?" "y"; then
      MYSQL_ADMIN_MODE="tcp"
      prompt MYSQL_ROOT_PASS "MySQL root password"
      mysql_admin_exec "SELECT 1;" >/dev/null 2>&1 || die "MySQL root access failed (tcp)."
    else
      die "MySQL root access failed. Install cannot continue."
    fi
  fi

  # Create DB + user (DB_NAME_SAFE / DB_USER_SAFE)
  mysql_admin_exec "CREATE DATABASE IF NOT EXISTS \`${DB_NAME_SAFE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql_admin_exec "CREATE USER IF NOT EXISTS '${DB_USER_SAFE}'@'%' IDENTIFIED BY '${DB_PASS}';"
  mysql_admin_exec "GRANT ALL PRIVILEGES ON \`${DB_NAME_SAFE}\`.* TO '${DB_USER_SAFE}'@'%';"
  mysql_admin_exec "FLUSH PRIVILEGES;"

  # Test app credentials
  log "Testing app DB credentials..."
  mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER_SAFE" -p"$DB_PASS" -e "USE \`$DB_NAME_SAFE\`; SELECT 1;" >/dev/null \
    || die "DB test failed for ${DB_USER_SAFE}@${DB_HOST}:${DB_PORT}/${DB_NAME_SAFE}"
}

#############################################
# .env creation (uncomments + correct values)
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
  [[ -f "${APP_DIR}/.env.example" ]] || die ".env.example not found in ${APP_DIR}"

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
  write_env_kv "$envfile" "DB_DATABASE" "$DB_NAME_SAFE"
  write_env_kv "$envfile" "DB_USERNAME" "$DB_USER_SAFE"
  write_env_kv "$envfile" "DB_PASSWORD" "$DB_PASS"

  # Avoid install-time failures before cache/sessions tables exist
  # (we’ll switch back after migrations)
  write_env_kv "$envfile" "CACHE_STORE" "file"
  write_env_kv "$envfile" "SESSION_DRIVER" "file"
  write_env_kv "$envfile" "QUEUE_CONNECTION" "database"
  write_env_kv "$envfile" "FILESYSTEM_DISK" "local"

  grep -Eq '^DB_DATABASE=' "$envfile" || die ".env DB_DATABASE not written"
  grep -Eq '^DB_USERNAME=' "$envfile" || die ".env DB_USERNAME not written"
  grep -Eq '^DB_PASSWORD=' "$envfile" || die ".env DB_PASSWORD not written"
}

#############################################
# Laravel install steps (safe order)
#############################################
laravel_install(){
  log "Preparing Laravel dirs..."
  run_as_app "cd '$APP_DIR' && mkdir -p storage bootstrap/cache"

  log "Removing stale bootstrap cache..."
  run_as_app "cd '$APP_DIR' && rm -f bootstrap/cache/config.php bootstrap/cache/services.php bootstrap/cache/packages.php"

  log "Composer install (no scripts to avoid early DB cache usage)..."
  run_as_app "cd '$APP_DIR' && '$COMPOSER_BIN' install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts"

  log "Generate app key..."
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan key:generate --force"

  log "Run package discovery (now app key exists)..."
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan package:discover --ansi"

  log "Run migrations..."
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan migrate --force"

  log "Seeding PortalPermissionsSeeder..."
  if run_as_app "cd '$APP_DIR' && $PHP_BIN -r 'require \"vendor/autoload.php\"; echo class_exists(\"Database\\\\Seeders\\\\PortalPermissionsSeeder\")?\"1\":\"0\";'" | grep -q '^1$'; then
    run_as_app "cd '$APP_DIR' && $PHP_BIN artisan db:seed --class='Database\\Seeders\\PortalPermissionsSeeder' --force"
  else
    warn "PortalPermissionsSeeder not found. Skipping."
  fi

  log "Switching CACHE_STORE/SESSION_DRIVER to database (post-migrate)..."
  local envfile="${APP_DIR}/.env"
  write_env_kv "$envfile" "CACHE_STORE" "database"
  write_env_kv "$envfile" "SESSION_DRIVER" "database"

  log "Clear caches..."
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan config:clear || true"
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan cache:clear || true"

  log "Build frontend assets..."
  if [[ -f "$APP_DIR/package.json" ]]; then
    run_as_app "cd '$APP_DIR' && $NPM_BIN ci || $NPM_BIN install"
    run_as_app "cd '$APP_DIR' && $NPM_BIN run build"
  else
    warn "package.json not found; skipping npm build."
  fi

  log "Optimize..."
  run_as_app "cd '$APP_DIR' && $PHP_BIN artisan optimize"
}

#############################################
# Create initial Admin user (make:admin)
#############################################
create_admin_user(){
  log "Creating initial Admin user (php artisan make:admin)..."

  prompt ADMIN_NAME "Admin full name" "Admin"
  prompt ADMIN_EMAIL "Admin email"

  prompt ADMIN_USERNAME "Admin username (optional; leave blank to auto-generate)" ""

  prompt_secret ADMIN_PASS "Admin password (min 10 chars)"
  ensure_min_len "$ADMIN_PASS" 10 "Admin password must be at least 10 characters."

  # Run as app user (non-interactive)
  if [[ -n "$ADMIN_USERNAME" ]]; then
    run_as_app "cd '$APP_DIR' && $PHP_BIN artisan make:admin --name=\"${ADMIN_NAME}\" --email=\"${ADMIN_EMAIL}\" --username=\"${ADMIN_USERNAME}\" --password=\"${ADMIN_PASS}\" --force"
  else
    run_as_app "cd '$APP_DIR' && $PHP_BIN artisan make:admin --name=\"${ADMIN_NAME}\" --email=\"${ADMIN_EMAIL}\" --password=\"${ADMIN_PASS}\" --force"
  fi
}

#############################################
# Nginx vhost + SSL
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
    [[ -n "$enabled" ]] && ln -sf "$avail" "$enabled"
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
    [[ -n "$enabled" ]] && ln -sf "$avail" "$enabled"
  fi

  nginx -t
  systemctl reload "$NGINX_SERVICE"
}

enable_ssl_letsencrypt(){
  [[ "$DOMAIN" != "_" ]] || die "Let’s Encrypt requires a real domain."
  log "Requesting Let’s Encrypt cert via certbot..."
  certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$LE_EMAIL" --redirect
  nginx -t
  systemctl reload "$NGINX_SERVICE"
}

#############################################
# Permissions
#############################################
fix_permissions(){
  log "Fixing permissions..."
  mkdir -p "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
  chown -R "$APP_USER":"$APP_USER" "$APP_DIR"
  chmod -R ug+rwX "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
  chgrp -R "$WEB_GROUP" "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" || true
}

#############################################
# Docker installation path
#############################################
write_docker_env(){ # file
  local file="$1"
  cat > "$file" <<EOF
# ---------------------------------------------------------------------------
# Generated by install.sh (Docker mode) — consumed by docker-compose.yml
# ---------------------------------------------------------------------------
APP_NAME=${APP_NAME}
APP_ENV=production
APP_DEBUG=false
APP_URL=${APP_URL}

WEB_PORT=${WEB_PORT}

DB_DATABASE=${DB_NAME_SAFE}
DB_USERNAME=${DB_USER_SAFE}
DB_PASSWORD=${DB_PASS}
DB_ROOT_PASSWORD=${DB_ROOT_PASS}
DB_EXPOSED_PORT=${DB_EXPOSED_PORT}

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

ADMIN_NAME=${ADMIN_NAME}
ADMIN_EMAIL=${ADMIN_EMAIL}
ADMIN_PASSWORD=${ADMIN_PASS}
EOF
}

docker_install(){
  log "=== Docker installation ==="

  command_exists docker || die "Docker is not installed. See https://docs.docker.com/engine/install/"
  if ! docker compose version >/dev/null 2>&1; then
    die "Docker Compose v2 plugin not found. Install it: https://docs.docker.com/compose/install/"
  fi

  local repo_root compose_file envfile
  repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
  compose_file="${repo_root}/docker-compose.yml"
  envfile="${repo_root}/.env"
  [[ -f "$compose_file" ]] || die "docker-compose.yml not found at ${repo_root}"

  prompt APP_NAME "App name" "assets.i-portal.me"
  prompt WEB_PORT "Host port to publish the app on" "8080"
  APP_URL="http://localhost:${WEB_PORT}"
  if yesno "Serve on a custom domain/URL instead of localhost?" "n"; then
    prompt APP_URL "Public application URL" "$APP_URL"
  fi

  prompt DB_NAME_RAW "Database name" "assets"
  DB_NAME_SAFE="$(to_safe_token "$DB_NAME_RAW")"
  prompt DB_USER_RAW "Database user" "assets"
  DB_USER_SAFE="$(to_safe_token "$DB_USER_RAW")"

  prompt_secret DB_PASS "Database password (min 8 chars)"
  ensure_min_len "$DB_PASS" 8 "Database password must be at least 8 characters."
  prompt_secret DB_ROOT_PASS "MySQL root password (min 8 chars)"
  ensure_min_len "$DB_ROOT_PASS" 8 "Root password must be at least 8 characters."

  prompt DB_EXPOSED_PORT "Host port to expose MySQL on (for local tools)" "3306"

  ADMIN_EMAIL=""
  ADMIN_PASS=""
  ADMIN_NAME="Admin"
  if yesno "Create an initial admin user automatically on first boot?" "y"; then
    prompt ADMIN_NAME "Admin full name" "Admin"
    prompt ADMIN_EMAIL "Admin email"
    prompt_secret ADMIN_PASS "Admin password (min 10 chars)"
    ensure_min_len "$ADMIN_PASS" 10 "Admin password must be at least 10 characters."
  fi

  if [[ -f "$envfile" ]]; then
    warn "An .env already exists at ${envfile}"
    if yesno "Back it up to .env.bak and overwrite with Docker settings?" "y"; then
      cp -f "$envfile" "${envfile}.bak"
    else
      die "Aborted. Remove or rename the existing .env first."
    fi
  fi

  log "Writing Docker env -> ${envfile}"
  write_docker_env "$envfile"

  log "Pulling latest MySQL image..."
  ( cd "$repo_root" && docker compose pull db )

  log "Building application image and starting the stack..."
  ( cd "$repo_root" && docker compose up -d --build )

  log "DONE (Docker)."
  echo "-------------------------------------------"
  echo "App name:     ${APP_NAME}"
  echo "URL:          ${APP_URL}"
  echo "Web port:     ${WEB_PORT}"
  echo "Database:     ${DB_NAME_SAFE} (user: ${DB_USER_SAFE})"
  echo "MySQL image:  mysql:latest"
  echo "Env file:     ${envfile}"
  echo "-------------------------------------------"
  echo "Useful commands:"
  echo "  docker compose ps"
  echo "  docker compose logs -f app"
  echo "  docker compose exec app php artisan make:admin"
  echo "  docker compose down            # stop"
  echo "  docker compose down -v         # stop + delete database volume"
  echo "-------------------------------------------"
}

#############################################
# Main
#############################################
main(){
  log "=== assets.i-portal.me Installer ==="
  echo "Choose installation type:"
  echo "  1) Regular   — bare-metal (Nginx + PHP-FPM + MySQL on this host)"
  echo "  2) Docker    — containerised stack (app + MySQL latest via docker compose)"
  local INSTALL_MODE=""
  prompt INSTALL_MODE "Installation type [1/2]" "1"

  if [[ "$INSTALL_MODE" == "2" || "$INSTALL_MODE" =~ ^[Dd] ]]; then
    docker_install
    return
  fi

  require_root
  detect_os

  log "=== Laravel Install (run from anywhere, multi-project safe) ==="
  list_existing_projects

  prompt PROJECT_SLUG_RAW "Project slug (folder under /opt). Can be domain-style" "assets.i-portal.me"
  PROJECT_SAFE="$(to_safe_token "$PROJECT_SLUG_RAW")"

  prompt APP_NAME "App name" "assets"
  prompt DOMAIN "Domain (e.g. assets.i-portal.me). Use '_' for HTTP/IP only" "assets.i-portal.me"

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

  if yesno "Deploy from Git repo?" "n"; then
    SOURCE_MODE="git"
    prompt GIT_REPO "Git repo URL (ssh/https)"
    prompt GIT_BRANCH "Git branch" "main"
  else
    SOURCE_MODE="copy"
    # default to where install.sh is located
    local default_src
    default_src="$(resolve_default_source_path)"
    prompt SOURCE_PATH "Local source path (Laravel project folder)" "$default_src"
  fi

  prompt DB_HOST "MySQL host" "localhost"
  prompt DB_PORT "MySQL port" "3306"
  prompt DB_NAME_RAW "DB name (dots not allowed; will be sanitized)" "${PROJECT_SLUG_RAW}"
  DB_NAME_SAFE="$(to_safe_token "$DB_NAME_RAW")"
  DB_USER_SAFE="$(to_safe_token "$PROJECT_SAFE")"

  prompt DB_PASS "DB password (will be stored in .env)"

  log "DB will be created as: ${DB_NAME_SAFE}"
  log "DB user will be:      ${DB_USER_SAFE}"

  if [[ "$ENABLE_HTTPS" == "yes" ]]; then
    if yesno "Use existing SSL cert files?" "y"; then
      SSL_MODE="existing"
      prompt CERT_FULLCHAIN "Fullchain path (.pem)" "/etc/ssl/${PROJECT_SAFE}.fullchain.pem"
      prompt CERT_PRIVKEY "Privkey path (.key)" "/etc/ssl/${PROJECT_SAFE}.privkey.pem"
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

  create_app_user_and_dir
  copy_or_clone_code

  check_nginx_collision

  setup_database
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
  create_admin_user

  systemctl restart "$PHP_FPM_SERVICE" || true
  systemctl reload "$NGINX_SERVICE" || true

  log "DONE."
  echo "-------------------------------------------"
  echo "Project slug:   ${PROJECT_SLUG_RAW}"
  echo "Safe token:     ${PROJECT_SAFE}"
  echo "Path:           ${APP_DIR}"
  echo "Linux user:     ${APP_USER}"
  echo "URL:            ${APP_URL}"
  echo "DB:             ${DB_NAME_SAFE} (user: ${DB_USER_SAFE})"
  local avail enabled
  read -r avail enabled < <(nginx_site_paths)
  echo "Nginx vhost:    ${avail}"
  echo "-------------------------------------------"
}

main "$@"
