#!/usr/bin/env bash
# E2E: upload documents with type + expiry, verify asset-page highlighting and
# dashboard expiry reminders, and a private download round-trip.
# Requires the "E2E Villa" asset (run money_flow.sh first).
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

echo "== Login =="
login && ok "logged in"

ASSET_ID=$(tk 'echo App\Models\Asset::where("name","E2E Villa")->value("id");')
if [ -z "$ASSET_ID" ]; then
  echo "  ! No 'E2E Villa' asset — run money_flow.sh first."; exit 1
fi
echo "Using asset id=$ASSET_ID"

# Clear prior E2E docs for a deterministic count.
tk "App\Models\AssetDocument::where('asset_id',${ASSET_ID})->whereIn('doc_type',['Insurance','Certificate'])->delete();" >/dev/null

WORK=$(mktemp -d); trap 'rm -rf "$WORK"' EXIT
printf 'Insurance policy (expired).\n' > "$WORK/insurance.txt"
printf 'Safety certificate (expiring soon).\n' > "$WORK/cert.txt"
EXPIRED=$(reldate -30d)
SOON=$(reldate +10d)

echo "== Upload expired Insurance (expires ${EXPIRED}) + expiring Certificate (expires ${SOON}) =="
curl -s -b "$JAR" -c "$JAR" -o /dev/null -w "  insurance upload http %{http_code}\n" \
  -F "_token=${T}" -F "doc_type=Insurance" -F "expires_at=${EXPIRED}" \
  -F "file=@${WORK}/insurance.txt;type=text/plain" "$BASE_URL/assets/${ASSET_ID}/documents"
curl -s -b "$JAR" -c "$JAR" -o /dev/null -w "  certificate upload http %{http_code}\n" \
  -F "_token=${T}" -F "doc_type=Certificate" -F "expires_at=${SOON}" \
  -F "file=@${WORK}/cert.txt;type=text/plain" "$BASE_URL/assets/${ASSET_ID}/documents"

[ "$(tk "echo App\Models\AssetDocument::where('asset_id',${ASSET_ID})->where('doc_type','Insurance')->count();")" -ge 1 ] \
  && ok "document persisted with type + expiry" || bad "document persist"

echo "== Asset page highlighting =="
SHOW=$(authget "assets/${ASSET_ID}")
echo "$SHOW" | grep -q "Expired" && ok "shows 'Expired' badge" || bad "expired badge"
echo "$SHOW" | grep -q "Soon"    && ok "shows 'Soon' badge"    || bad "soon badge"
echo "$SHOW" | grep -q "table-danger" && ok "expired row highlighted" || bad "row highlight"

echo "== Dashboard reminders =="
EXP=$(tk 'echo App\Models\AssetDocument::whereNotNull("expires_at")->whereDate("expires_at","<",now())->count();')
SOONC=$(tk 'echo App\Models\AssetDocument::whereNotNull("expires_at")->whereDate("expires_at",">=",now())->whereDate("expires_at","<=",now()->addDays(30))->count();')
{ [ "${EXP:-0}" -ge 1 ] && [ "${SOONC:-0}" -ge 1 ]; } && ok "expired=$EXP, expiring=$SOONC" || bad "reminder counts"
authget "dashboard" | grep -q "expiring ≤30d" && ok "dashboard reminder widget present" || bad "dashboard widget"

echo "== Download round-trip =="
DID=$(tk "echo App\Models\AssetDocument::where('asset_id',${ASSET_ID})->latest('id')->value('id');")
code=$(curl -s -b "$JAR" -o /dev/null -w "%{http_code}" "$BASE_URL/assets/${ASSET_ID}/documents/${DID}/download")
[ "$code" = "200" ] && ok "download 200" || bad "download ($code)"

summary
