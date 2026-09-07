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
  local msg="$1" def="${2:-n}" ans=""
  read -r -p "$msg [${def}]: " ans
  ans="${ans:-$def}"
  [[ "$ans" =~ ^[Yy]$ ]]
}

to_safe_token(){
  local s="$1"
  s="$(echo "$s" | tr '[:upper:]' '[:lower:]')"
  s="$(echo "$s" | sed -E 's/[^a-z0-9_]+/_/g; s/^_+//; s/_+$//; s/__+/_/g')"
  [[ -n "$s" ]] || s="app"
  echo "$s"
}

detect_os_family(){
  if [[ -r /etc/os-release ]]; then
    # shellcheck disable=SC1091
    . /etc/os-release
  fi
  if [[ "${ID:-}" =~ (ubuntu|debian) ]] || [[ "${ID_LIKE:-}" =~ (debian|ubuntu) ]]; then
    echo "debian"
  else
    echo "rhel"
  fi
}

mysql_admin_exec_socket(){
  local sql="$1"
  mysql -u root -e "$sql"
}

main(){
  require_root

  local OS_FAMILY
  OS_FAMILY="$(detect_os_family)"

  local PROJECT_SLUG_RAW PROJECT_SAFE APP_DIR
  prompt PROJECT_SLUG_RAW "Project slug (folder under /opt)" "assets.i-portal.me"
  PROJECT_SAFE="$(to_safe_token "$PROJECT_SLUG_RAW")"
  APP_DIR="/opt/${PROJECT_SLUG_RAW}"

  log "Will uninstall:"
  echo "  - Folder:     ${APP_DIR}"
  echo "  - Linux user: ${PROJECT_SAFE}"
  echo "  - Nginx conf: ${PROJECT_SAFE}.conf"

  if ! yesno "Are you 100% sure you want to continue?" "n"; then
    die "Aborted."
  fi

  # Nginx vhost removal
  log "Removing Nginx vhost (project-only)..."
  if [[ "$OS_FAMILY" == "debian" ]]; then
    rm -f "/etc/nginx/sites-enabled/${PROJECT_SAFE}.conf" || true
    rm -f "/etc/nginx/sites-available/${PROJECT_SAFE}.conf" || true
  else
    rm -f "/etc/nginx/conf.d/${PROJECT_SAFE}.conf" || true
  fi

  if command -v nginx >/dev/null 2>&1; then
    nginx -t || warn "nginx -t failed (check configs)."
    systemctl reload nginx || true
  fi

  # Remove app directory
  if [[ -d "$APP_DIR" ]]; then
    log "Removing application directory: ${APP_DIR}"
    rm -rf "$APP_DIR"
  else
    warn "Directory not found: ${APP_DIR}"
  fi

  # Remove Linux user (only if exists)
  if id -u "$PROJECT_SAFE" >/dev/null 2>&1; then
    log "Removing Linux user: ${PROJECT_SAFE}"
    # remove home as well
    userdel -r "$PROJECT_SAFE" || true
  else
    warn "User not found: ${PROJECT_SAFE}"
  fi

  # Optional DB cleanup
  if yesno "Do you want to DROP the MySQL database and user for this project?" "n"; then
    local DB_NAME_RAW DB_NAME_SAFE DB_USER_SAFE
    prompt DB_NAME_RAW "DB name used (will be sanitized)" "$PROJECT_SLUG_RAW"
    DB_NAME_SAFE="$(to_safe_token "$DB_NAME_RAW")"
    DB_USER_SAFE="$(to_safe_token "$PROJECT_SAFE")"

    warn "This will DROP database '${DB_NAME_SAFE}' and user '${DB_USER_SAFE}'"
    if yesno "Confirm DROP DATABASE + DROP USER?" "n"; then
      log "Dropping DB + user (socket root)..."
      mysql_admin_exec_socket "DROP DATABASE IF EXISTS \`${DB_NAME_SAFE}\`;"
      mysql_admin_exec_socket "DROP USER IF EXISTS '${DB_USER_SAFE}'@'%';"
      mysql_admin_exec_socket "FLUSH PRIVILEGES;"
    else
      warn "DB drop skipped."
    fi
  fi

  # Optional SSL cleanup
  if yesno "Remove SSL cert files you provided (ONLY if they are dedicated for this project)?" "n"; then
    local CERT_FULLCHAIN CERT_PRIVKEY
    prompt CERT_FULLCHAIN "Fullchain path" "/etc/ssl/${PROJECT_SAFE}.fullchain.pem"
    prompt CERT_PRIVKEY "Privkey path" "/etc/ssl/${PROJECT_SAFE}.privkey.pem"
    rm -f "$CERT_FULLCHAIN" "$CERT_PRIVKEY" || true
    log "SSL cert files removed (if existed)."
  fi

  log "UNINSTALL DONE."
}

main "$@"
