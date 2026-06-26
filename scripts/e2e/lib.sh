#!/usr/bin/env bash
# Shared helpers for the end-to-end smoke scripts.
#
# Configurable via env:
#   BASE_URL    (default http://localhost:8080)
#   ADMIN_USER  (default admin)
#   ADMIN_PASS  (default ChangeMe123!)
#
# Requires the Docker stack to be running (uses `docker compose exec` to read
# back ids via tinker). Run the scripts from anywhere — they cd to the repo root.

set -uo pipefail

# Always operate from the repository root (two levels up from scripts/e2e/).
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

BASE_URL="${BASE_URL:-http://localhost:8080}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-ChangeMe123!}"

PASS=0
FAIL=0
ok()  { echo "  ✓ $1"; PASS=$((PASS + 1)); }
bad() { echo "  ✗ $1"; FAIL=$((FAIL + 1)); }

JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

# Run a one-line tinker expression in the app container (used to read ids).
tk() { docker compose exec -T app php artisan tinker --execute="$1" 2>/dev/null | tail -1 | tr -d '[:space:]'; }

# A date relative to today in YYYY-MM-DD (portable across macOS/BSD and GNU).
reldate() { # offset e.g. "-60d" or "+10d"
  date -v"$1" +%Y-%m-%d 2>/dev/null || date -d "${1/d/ days}" +%Y-%m-%d
}

# Log in as the admin and capture the session CSRF token into $T.
login() {
  local page lt
  page=$(curl -s -c "$JAR" "$BASE_URL/login")
  lt=$(echo "$page" | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')
  curl -s -b "$JAR" -c "$JAR" -o /dev/null -d "_token=${lt}" \
    --data-urlencode "username=${ADMIN_USER}" --data-urlencode "password=${ADMIN_PASS}" "$BASE_URL/login"
  # Read the session token from /profile (always reachable, even when
  # REQUIRE_2FA_FOR_ADMINS forces admins away from the dashboard).
  T=$(curl -s -b "$JAR" -c "$JAR" "$BASE_URL/profile" \
    | grep -oE 'name="csrf-token" content="[^"]+"' | head -1 | sed -E 's/.*content="([^"]+)".*/\1/')
  [ -n "${T:-}" ] || { echo "  ✗ login failed (is the stack up at ${BASE_URL}?)"; exit 1; }
}

# POST helper (adds the session token). Echoes the HTTP status.
post() { curl -s -b "$JAR" -c "$JAR" -o /dev/null -w "%{http_code}" -d "_token=${T}" "$@"; }

# GET helper (authenticated). Echoes the body.
authget() { curl -s -b "$JAR" "$BASE_URL/$1"; }

summary() { echo; echo "RESULT: ${PASS} passed, ${FAIL} failed"; [ "$FAIL" -eq 0 ]; }
