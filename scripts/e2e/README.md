# End-to-end smoke tests

Black-box scripts that drive the **running app over HTTP** (with CSRF) to verify
the main user journeys end to end. They complement the PHPUnit suite (which tests
units/features against SQLite) by exercising the real Dockerised stack.

## Prerequisites

- The Docker stack is up: `docker compose up -d --build`
- An admin account exists (default `admin` / `ChangeMe123!`)

> The scripts use `docker compose exec app php artisan tinker` to read back ids,
> so they must run from a host that can reach the `app` container.

## Usage

```bash
# all flows
./scripts/e2e/run-all.sh

# or individually
./scripts/e2e/money_flow.sh
./scripts/e2e/documents_flow.sh   # run after money_flow.sh (needs the E2E asset)
./scripts/e2e/ops_flow.sh
```

Configure via env vars (defaults shown):

```bash
BASE_URL=http://localhost:8080 ADMIN_USER=admin ADMIN_PASS='ChangeMe123!' \
  ./scripts/e2e/run-all.sh
```

## What they cover

| Script | Flow |
|--------|------|
| `money_flow.sh` | asset → tenant → agreement → payments (incl. overdue) → expense → base currency + FX → **P&L report** (per-asset income/expenses/net incl. FX) + **CSV export** |
| `documents_flow.sh` | upload documents with **type + expiry** → asset-page **Expired/Soon** highlighting → **dashboard reminders** → private download |
| `ops_flow.sh` | `/health` probe → **DB backup** script → **enforce-2FA-for-admins** toggle (and revert) |

## Notes

- `money_flow.sh` cleans up its own `E2E Villa` / `E2E Tenant` data first, so it is
  re-runnable. It asserts the **per-asset** P&L row (isolated from other data).
- These create demo records (`E2E *`). They are removed on the next money-flow run.
- `ops_flow.sh` toggles `REQUIRE_2FA_FOR_ADMINS` in the container `.env` and reverts
  it; it does not change anything in the repo.
