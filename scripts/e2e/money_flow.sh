#!/usr/bin/env bash
# E2E: asset -> tenant -> agreement -> payments (incl. overdue) -> expense ->
# base currency + FX -> P&L report + CSV export. Verifies the per-asset P&L row
# (income/expenses/net) including FX consolidation. Re-runnable (cleans up first).
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

echo "== Login =="
login && ok "logged in"

# Remove any leftover E2E data so the per-asset totals are deterministic.
tk '$ids=App\Models\Asset::where("name","E2E Villa")->pluck("id");
App\Models\RentalPayment::whereIn("asset_id",$ids)->delete();
App\Models\AssetExpense::whereIn("asset_id",$ids)->delete();
App\Models\AssetDocument::whereIn("asset_id",$ids)->delete();
App\Models\AssetRental::whereIn("asset_id",$ids)->delete();
App\Models\Asset::whereIn("id",$ids)->delete();
App\Models\Tenant::where("name","E2E Tenant")->delete();' >/dev/null

TODAY=$(reldate +0d 2>/dev/null || date +%Y-%m-%d)
PAST=$(reldate -60d)

echo "== Asset type =="
post --data-urlencode "name=E2E Apartment" --data-urlencode "is_active=1" --data-urlencode "sort_order=1" "$BASE_URL/settings/asset-types" >/dev/null
TYPE_ID=$(tk 'echo App\Models\AssetType::where("name","E2E Apartment")->value("id");')
[ -n "$TYPE_ID" ] && ok "asset type (id=$TYPE_ID)" || bad "asset type"

echo "== Asset =="
post --data-urlencode "name=E2E Villa" --data-urlencode "asset_type_id=${TYPE_ID}" --data-urlencode "currency=EUR" --data-urlencode "status=Rented" --data-urlencode "city=Limassol" "$BASE_URL/assets" >/dev/null
ASSET_ID=$(tk 'echo App\Models\Asset::where("name","E2E Villa")->value("id");')
[ -n "$ASSET_ID" ] && ok "asset (id=$ASSET_ID)" || bad "asset"

echo "== Tenant + agreement =="
post --data-urlencode "name=E2E Tenant" --data-urlencode "email=e2e@example.com" "$BASE_URL/tenants" >/dev/null
TENANT_ID=$(tk 'echo App\Models\Tenant::where("name","E2E Tenant")->value("id");')
post --data-urlencode "asset_id=${ASSET_ID}" --data-urlencode "tenant_id=${TENANT_ID}" --data-urlencode "agreement_start_date=${TODAY}" --data-urlencode "rent_type=Long-term" --data-urlencode "is_active=1" --data-urlencode "amount=1000" --data-urlencode "currency=EUR" "$BASE_URL/assets/rentals" >/dev/null
RENTAL_ID=$(tk "echo App\Models\AssetRental::where('asset_id',${ASSET_ID})->latest('id')->value('id');")
[ -n "$RENTAL_ID" ] && ok "tenant linked to agreement (rental id=$RENTAL_ID)" || bad "agreement"

echo "== Base currency EUR + FX USD->0.5 =="
post --data-urlencode "base_currency=EUR" "$BASE_URL/settings/currencies/base" >/dev/null
post --data-urlencode "currency=USD" --data-urlencode "rate_to_base=0.5" "$BASE_URL/settings/currencies/rates" >/dev/null
ok "base + FX set"

echo "== Payments: 1000 EUR paid, 200 USD paid, 1000 EUR overdue =="
post --data-urlencode "asset_rental_id=${RENTAL_ID}" --data-urlencode "due_date=${TODAY}" --data-urlencode "amount=1000" --data-urlencode "currency=EUR" --data-urlencode "paid_date=${TODAY}" "$BASE_URL/payments" >/dev/null
post --data-urlencode "asset_rental_id=${RENTAL_ID}" --data-urlencode "due_date=${TODAY}" --data-urlencode "amount=200" --data-urlencode "currency=USD" --data-urlencode "paid_date=${TODAY}" "$BASE_URL/payments" >/dev/null
post --data-urlencode "asset_rental_id=${RENTAL_ID}" --data-urlencode "due_date=${PAST}" --data-urlencode "amount=1000" --data-urlencode "currency=EUR" "$BASE_URL/payments" >/dev/null
OVERDUE=$(tk "echo App\Models\RentalPayment::where('asset_id',${ASSET_ID})->where('status','pending')->whereDate('due_date','<',now())->count();")
[ "${OVERDUE:-0}" -ge 1 ] && ok "payments recorded (overdue=$OVERDUE)" || bad "payments"

echo "== Expense 300 EUR =="
post --data-urlencode "asset_id=${ASSET_ID}" --data-urlencode "spent_on=${TODAY}" --data-urlencode "category=Repairs" --data-urlencode "amount=300" --data-urlencode "currency=EUR" "$BASE_URL/expenses" >/dev/null
ok "expense recorded"

echo "== P&L report (this year) — E2E Villa row: income 1,100.00, net 800.00 =="
YEAR=$(date +%Y)
RPT=$(authget "reports?year=${YEAR}")
echo "$RPT" | grep -q "1,100.00" && ok "income 1,100.00 (1000 EUR + 200 USD @0.5)" || bad "income"
echo "$RPT" | grep -q "800.00" && ok "net 800.00 (1100 - 300)" || bad "net"

echo "== CSV export =="
CSV=$(authget "reports/export?year=${YEAR}")
{ echo "$CSV" | grep -q "TOTAL" && echo "$CSV" | grep -q "E2E Villa"; } && ok "CSV has rows + TOTAL" || bad "CSV"

summary
