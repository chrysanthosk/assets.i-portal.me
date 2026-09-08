#!/usr/bin/env bash
set -euo pipefail

#############################################
# docker-preflight.sh
#
# Prepares the repo-root .env for `docker compose up` so a deploy never
# falls back to compose defaults. Idempotent — safe to run on every deploy.
#
#   1) .env exists        — created from .env.docker.example if missing
#                           (with random DB passwords on first creation)
#   2) APP_KEY is set     — generated once and persisted in .env so it survives
#                           image rebuilds (2FA secrets are encrypted with it)
#   3) Timezone           — APP_TIMEZONE confirmed (default Europe/Athens)
#   4) Port is free       — WEB_PORT is moved to the next free host port when
#                           another process or stack already listens on it
#
# Usage:  scripts/docker-preflight.sh            (from anywhere)
#         sourced by install.sh / new_deploy.sh  (calls docker_preflight)
#############################################

# Colourised logging — only define when not already provided by the caller.
if ! declare -F log >/dev/null 2>&1; then
  log()  { echo -e "\n\033[1;32m[INFO]\033[0m $*"; }
  warn() { echo -e "\n\033[1;33m[WARN]\033[0m $*"; }
  err()  { echo -e "\n\033[1;31m[ERR ]\033[0m $*" >&2; }
  die()  { err "$*"; exit 1; }
fi

PREFLIGHT_REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PREFLIGHT_ENV="${PREFLIGHT_REPO_ROOT}/.env"
PREFLIGHT_EXAMPLE="${PREFLIGHT_REPO_ROOT}/.env.docker.example"
PORT_SEARCH_RANGE=100   # how far above the configured port to look for a free one

# --- .env helpers ----------------------------------------------------------

env_get(){ # key -> value (empty when absent). Strips surrounding quotes.
  local key="$1" line
  line="$(grep -E "^${key}=" "$PREFLIGHT_ENV" 2>/dev/null | tail -n1 || true)"
  line="${line#*=}"
  line="${line%\"}"; line="${line#\"}"
  line="${line%\'}"; line="${line#\'}"
  printf '%s' "$line"
}

env_set(){ # key value — replace in place or append
  local key="$1" val="$2"
  if grep -qE "^${key}=" "$PREFLIGHT_ENV"; then
    # '|' is safe as sed delimiter: base64 and URLs never contain it
    sed -i.bak -E "s|^${key}=.*|${key}=${val}|" "$PREFLIGHT_ENV" && rm -f "${PREFLIGHT_ENV}.bak"
  else
    printf '%s=%s\n' "$key" "$val" >> "$PREFLIGHT_ENV"
  fi
}

random_secret(){ # url-safe, 24 chars
  if command -v openssl >/dev/null 2>&1; then
    openssl rand -base64 32 | tr -d '/+=' | cut -c1-24
  else
    head -c 64 /dev/urandom | base64 | tr -d '/+=\n' | cut -c1-24
  fi
}

# --- port helpers ----------------------------------------------------------

port_listening(){ # port -> 0 when something on the host listens on it
  local port="$1"
  if command -v ss >/dev/null 2>&1; then
    [[ -n "$(ss -ltnH "( sport = :${port} )" 2>/dev/null)" ]]
  elif command -v lsof >/dev/null 2>&1; then
    lsof -nP -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1
  else
    (exec 3<>"/dev/tcp/127.0.0.1/${port}") >/dev/null 2>&1
  fi
}

# Host ports currently published by THIS compose project. Those are not a
# conflict: `compose up` recreates the containers that hold them.
own_stack_ports(){
  local ids
  ids="$(cd "$PREFLIGHT_REPO_ROOT" && docker compose ps -aq 2>/dev/null || true)"
  [[ -n "$ids" ]] || return 0
  # shellcheck disable=SC2086
  docker inspect --format '{{range $k,$v := .NetworkSettings.Ports}}{{range $v}}{{.HostPort}} {{end}}{{end}}' $ids 2>/dev/null \
    | tr ' ' '\n' | grep -E '^[0-9]+$' | sort -u || true
}

port_conflicts(){ # port -> 0 when a foreign process/stack holds it
  local port="$1"
  port_listening "$port" || return 1
  own_stack_ports | grep -qx "$port" && return 1
  return 0
}

next_free_port(){ # start -> first port >= start with no listener
  local p="$1" end=$(( $1 + PORT_SEARCH_RANGE ))
  while (( p <= end )); do
    if ! port_conflicts "$p"; then echo "$p"; return 0; fi
    p=$(( p + 1 ))
  done
  return 1
}

# --- steps -----------------------------------------------------------------

preflight_env_file(){
  if [[ -f "$PREFLIGHT_ENV" ]]; then
    log ".env present at ${PREFLIGHT_ENV}"
    return 0
  fi
  [[ -f "$PREFLIGHT_EXAMPLE" ]] || die "Neither .env nor .env.docker.example found in ${PREFLIGHT_REPO_ROOT}"

  warn "No .env at repo root — creating it from .env.docker.example"
  cp "$PREFLIGHT_EXAMPLE" "$PREFLIGHT_ENV"
  chmod 600 "$PREFLIGHT_ENV" || true

  # Fresh file: never ship the placeholder DB passwords. MySQL only reads these
  # on first initialisation of the db_data volume, so this is the one safe moment.
  env_set DB_PASSWORD "$(random_secret)"
  env_set DB_ROOT_PASSWORD "$(random_secret)"
  log "Generated random DB_PASSWORD / DB_ROOT_PASSWORD"

  warn "Review ${PREFLIGHT_ENV}: APP_URL and ADMIN_* still hold example values (login is admin / ChangeMe123!)."
}

preflight_app_key(){
  local key
  key="$(env_get APP_KEY)"
  if [[ "$key" =~ ^base64:.+ ]]; then
    log "APP_KEY already set — keeping it (rotating it would invalidate 2FA secrets)"
    return 0
  fi
  local raw
  if command -v openssl >/dev/null 2>&1; then
    raw="$(openssl rand -base64 32)"
  else
    raw="$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
  fi
  env_set APP_KEY "base64:${raw}"
  log "Generated APP_KEY and saved it to .env (persists across rebuilds)"
}

preflight_port(){ # key default(empty = feature disabled when key blank)
  local key="$1" def="$2" port new
  port="$(env_get "$key")"
  if ! grep -qE "^${key}=" "$PREFLIGHT_ENV"; then
    port="$def"   # key absent: compose would apply this default
  fi
  if [[ -z "$port" ]]; then
    log "${key} is empty — port publishing disabled, nothing to check"
    return 0
  fi
  [[ "$port" =~ ^[0-9]+$ ]] || die "${key}='${port}' in .env is not a valid port number"

  if ! port_conflicts "$port"; then
    log "${key}=${port} is free (or held by this stack)"
    return 0
  fi

  new="$(next_free_port $(( port + 1 )))" || die "No free port found in ${port}..$(( port + PORT_SEARCH_RANGE )) for ${key}"
  warn "${key}=${port} is already in use on this host — switching to ${new}"
  env_set "$key" "$new"

  # Keep a localhost APP_URL in step with the published web port.
  if [[ "$key" == "WEB_PORT" ]]; then
    local url
    url="$(env_get APP_URL)"
    if [[ -z "$url" || "$url" =~ ^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?/?$ ]]; then
      env_set APP_URL "http://localhost:${new}"
      log "APP_URL updated to http://localhost:${new}"
    else
      warn "APP_URL=${url} left unchanged — make sure your reverse proxy targets port ${new}"
    fi
  fi
}

# Timezone drives the daily reminder times and due dates. Asked (with the current
# value as default) when running interactively; defaults silently otherwise.
preflight_timezone(){
  local current tz
  current="$(env_get APP_TIMEZONE)"
  tz="${current:-Europe/Athens}"
  if [[ -t 0 ]]; then
    read -r -p "Timezone for due dates and reminders [${tz}]: " answer
    tz="${answer:-$tz}"
  fi
  if command -v php >/dev/null 2>&1; then
    php -r 'exit(in_array($argv[1], timezone_identifiers_list(), true) ? 0 : 1);' "$tz" 2>/dev/null \
      || die "Unknown timezone '${tz}'. Use an IANA name such as Europe/Athens or Asia/Dubai."
  fi
  if [[ "$tz" != "$current" ]]; then
    env_set APP_TIMEZONE "$tz"
    log "APP_TIMEZONE set to ${tz}"
  else
    log "APP_TIMEZONE=${tz}"
  fi
}

docker_preflight(){
  log "=== Docker preflight (.env / APP_KEY / timezone / port) ==="
  command -v docker >/dev/null 2>&1 || die "Docker is not installed."
  preflight_env_file
  preflight_app_key
  preflight_timezone
  preflight_port WEB_PORT 8080
  if [[ -n "$(env_get DB_EXPOSED_PORT)" ]]; then
    warn "DB_EXPOSED_PORT is set but no longer used: MySQL is not published on the host. To reach it with a local tool, add a ports entry for db in docker-compose.override.yml."
  fi
  log "Preflight OK: WEB_PORT=$(env_get WEB_PORT) APP_URL=$(env_get APP_URL)"
}

# Run directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
  docker_preflight
fi
