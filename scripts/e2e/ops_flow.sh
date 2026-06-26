#!/usr/bin/env bash
# E2E: /health probe, DB backup script, and the enforce-2FA-for-admins toggle.
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

echo "== /health =="
H=$(curl -s -w "\n%{http_code}" "$BASE_URL/health")
{ echo "$H" | grep -q '"status":"ok"' && echo "$H" | tail -1 | grep -q 200; } \
  && ok "health 200 + status ok" || bad "health"

echo "== DB backup script =="
BACKUP_DIR="$(mktemp -d)"
if BACKUP_DIR="$BACKUP_DIR" RETENTION_DAYS=14 bash scripts/backup-db.sh >/tmp/e2e-bk.log 2>&1; then
  f=$(ls "$BACKUP_DIR"/*.sql.gz 2>/dev/null | head -1)
  if [ -n "$f" ] && gzip -t "$f" 2>/dev/null; then
    ok "backup produced valid gzip ($(gzip -dc "$f" | grep -c 'CREATE TABLE') tables)"
  else bad "backup output"; fi
else bad "backup script (see /tmp/e2e-bk.log)"; fi
rm -rf "$BACKUP_DIR"

echo "== Enforce 2FA for admins (live toggle, then revert) =="
revert_2fa() { docker compose exec -T app sh -c \
  'sed -i "s/^REQUIRE_2FA_FOR_ADMINS=.*/REQUIRE_2FA_FOR_ADMINS=false/" .env; php artisan optimize' >/dev/null 2>&1; }

docker compose exec -T app sh -c \
  'grep -q "^REQUIRE_2FA_FOR_ADMINS=" .env || echo "REQUIRE_2FA_FOR_ADMINS=false" >> .env; \
   sed -i "s/^REQUIRE_2FA_FOR_ADMINS=.*/REQUIRE_2FA_FOR_ADMINS=true/" .env; php artisan config:clear' >/dev/null 2>&1
# Safety net: always revert enforcement on exit, even if an assertion fails.
trap 'revert_2fa; rm -f "$JAR"' EXIT

login
LOC=$(curl -s -b "$JAR" -o /dev/null -w "%{redirect_url}" "$BASE_URL/dashboard")
echo "$LOC" | grep -q "/profile" && ok "admin without 2FA forced to /profile" || bad "enforcement redirect ($LOC)"
[ "$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' "$BASE_URL/profile")" = "200" ] && ok "/profile reachable to enroll" || bad "profile reachable"

revert_2fa
[ "$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' "$BASE_URL/dashboard")" = "200" ] && ok "reverted: /dashboard 200" || bad "revert"

summary
