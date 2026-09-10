# assets.i-portal.me

A **Laravel 13 + Bootstrap 5** portal for a private property portfolio: properties,
tenants, agreements, a monthly rent-check loop, expenses, profit & loss, documents —
with AI reading of title deeds, contracts and manager statements. Dark/light,
mobile-friendly, deployed with Docker.

---

## What it does

**Portfolio**
- **Properties** — purchase, financing, title deed, location and size, documents with
  expiry dates. Create one by hand or **import a title deed scan** (Cyprus Land Registry
  sheets and Dubai DIFC/DLD deeds): the registry references, areas, owners and
  valuations are read for you, the scan is filed as the deed document.
- **Tenants** — created automatically from agreements; email, phone and ID are read
  from the contract. **Fill from contracts** catches up older agreements.

**Rent**
- **Agreements** — one per tenancy or management contract. Monthly rent (in advance or
  *paid the month after*), with its own due day, or **fixed instalments that repeat every
  contract year** (e.g. an annual guarantee split 15 % on 15 Apr, 15 % on 31 May …).
  **Import a contract PDF** (English or Greek) and the parties, period, currency and
  schedule are proposed; the PDF is filed with the property.
- **Rent check** — the expected payment for every agreement is generated automatically,
  including everything already due this year when an agreement is added. One daily email
  lists what is waiting for your answer with **Yes / No** links per row (repeating every
  few days until answered). Variable payouts: correct the amount, or **upload the
  manager's statement** and the net payable is read from it.
- **Payments** — every expected and one-off payment, overdue and not-received tracking.

**Finance**
- **Expenses** — categorised costs per property, any currency.
- **Reports** — yearly P&L per property and per month, rent collection rate, CSV export,
  all converted to your base currency through the FX rates you enter.
- **Dashboard** — properties, portfolio value, contracted rent, rent received this year
  and the collection rate, outstanding, net; panels for rent to confirm, overdue rent,
  expiring documents and recent activity.

**Platform**
- Username + password, 2FA (Google Authenticator) with recovery codes, email-change OTP,
  password strength meter, security headers, encrypted sessions, audit log.
- **Simple by default**: users, permission sets, owner entities, tags, currencies & FX and
  the audit log are hidden until *Advanced features* is switched on (Settings → Portal).
  Currencies & FX appears on its own as soon as a second currency is in use.
- Registration is closed: accounts are created by an admin (Settings → Users or
  `php artisan make:admin`).

---

## Requirements

Production runs in Docker; nothing else is installed on the host.

- Docker Engine 24+ with the Compose v2 plugin.
- A reverse proxy for HTTPS (the container serves plain HTTP on `WEB_PORT`).
- An **Anthropic API key** if you want deed / contract / statement reading
  (Settings → Portal, or `ANTHROPIC_API_KEY`). Everything else works without it.

For local development without Docker: PHP 8.3+ (the image uses 8.5), Composer,
Node 22 / npm. SQLite is used by default.

---

## Install (Docker)

```bash
git clone git@github.com:chrysanthosk/assets.i-portal.me.git
cd assets.i-portal.me
./scripts/install.sh
```

The installer asks for the app name, host port, public URL, database passwords, the
first admin account and optionally the Anthropic key; writes `.env`; runs the preflight
(persistent `APP_KEY`, timezone, free port); then builds the image and starts the stack.

Run it as the user who will run deploys later, so `.env` stays readable to them.

| Service | Runs |
|---------|------|
| `app`   | Nginx + PHP-FPM + queue worker + scheduler, one image managed by Supervisor |
| `db`    | MySQL (`mysql:latest`) on the `db_data` volume, **not published on the host** |

On first boot the app container creates its own `.env`, applies the host `APP_KEY`,
waits for MySQL, runs `migrate --force` (additive, never drops data), seeds
roles/permissions, creates the admin from `ADMIN_*`, links storage and runs `optimize`.

### Log in

Log in with the **username** (default `admin`), not the email. Change the password
straight away. Then:

1. **Settings → Email (SMTP)** — enable, fill in, send the test email. All reminders use it.
2. **Settings → Portal** — reminder recipient, due day, Anthropic key.
3. **Settings → Currencies & FX** — needed only when you hold a second currency.

### Reverse proxy

Put your proxy in front of `http://<host>:<WEB_PORT>` and set `APP_URL` to the public
`https://` address. On a shared Docker host, attach the app to the proxy's network with a
gitignored `docker-compose.override.yml`:

```yaml
services:
  app:
    networks: [portal, proxy]
networks:
  proxy:
    external: true
```

---

## Update

```bash
git pull --ff-only
./scripts/new_deploy.sh          # add --pull to also fetch the latest MySQL image
```

The script runs the preflight, rebuilds the image, recreates the containers, waits for
the app to answer and shows the status. Volumes (`db_data`, `app_storage`) are kept.
Its only prompt is the timezone (default `Europe/Athens`), skipped when there is no
terminal.

`scripts/docker-preflight.sh` (run by both scripts, or on its own) makes sure that:
- `.env` exists (created from `.env.docker.example` with random DB passwords),
- `APP_KEY` is set once and kept — **never rotate it**, 2FA secrets are encrypted with it,
- `APP_TIMEZONE` is set,
- `WEB_PORT` is free, moving to the next free port if another stack uses it.

---

## Day to day

| Task | Command |
|------|---------|
| Artisan | `docker compose exec app php artisan <cmd>` |
| Create an admin | `docker compose exec app php artisan make:admin` |
| Logs | `docker compose logs -f app` |
| Generate due payments now | `docker compose exec app php artisan rent:generate-due` |
| Send the rent-check digest now | `docker compose exec app php artisan rent:send-reminders --force` |
| Document expiry digest now | `docker compose exec app php artisan documents:send-expiry-reminders --force` |
| Stop / start | `docker compose down` / `docker compose up -d` |

Scheduled inside the container (`APP_TIMEZONE`): `rent:generate-due` daily 06:00,
`rent:send-reminders` daily 08:00, `documents:send-expiry-reminders` Mondays 08:15.

> Never run `migrate:fresh`, `db:wipe` or `docker compose down -v` in production —
> they delete the database.

### Backups

`./scripts/backup.sh` dumps MySQL to `backups/<db>-<stamp>.sql.gz` and archives the
uploaded files (`storage/app`: deeds, contracts, statements) to
`backups/storage-<stamp>.tar.gz`, keeping `RETENTION_DAYS` (default 14).

```bash
sudo ./scripts/backup.sh --install-cron 02:30   # nightly: systemd timer, or /etc/cron.d if cron runs
```

Set `BACKUP_REMOTE=user@host:/path` in `.env` to rsync each new archive off the server.

Restore:

```bash
gunzip -c backups/assets-<stamp>.sql.gz | docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASSWORD" assets
docker compose exec -T app tar -xzf - -C /var/www/html/storage < backups/storage-<stamp>.tar.gz
```

### Security options

| Setting | Where | Effect |
|---------|-------|--------|
| `REQUIRE_2FA_FOR_ADMINS=true` | `.env` | Admins must enrol in 2FA before using the portal |
| `SENTRY_LARAVEL_DSN` | `.env` | Error tracking; blank = off |
| Advanced features | Settings → Portal | Shows users, permission sets, owner entities, tags, FX, audit log |

The app trusts the private-network reverse proxy for the scheme, so HSTS and secure
cookies work behind HTTPS termination. `GET /health` reports database connectivity.

---

## Local development

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate      # SQLite
php artisan migrate && php artisan db:seed --class=PortalBootstrapSeeder
npm run dev                                           # or npm run build
php artisan serve
```

- `php artisan test` — PHPUnit on SQLite in memory (100 tests). AI reading is faked in
  tests; nothing calls the API.
- `./vendor/bin/pint` — code style (CI runs `pint --test`, the Vite build and PHPUnit).
- `scripts/e2e/` — optional HTTP smoke tests against a running Docker stack.

The test suite is not shipped in the production image, so `php artisan test` does not
run inside the container.

---

## Permissions

Routes are guarded by Spatie permissions synced from `config/portal_permissions.php`
(`PortalPermissionsSeeder`, run on every container start). Admins hold all of them.

| Permission | Grants |
|------------|--------|
| `view_dashboard` | Dashboard |
| `manage_assets`, `manage_asset_types`, `manage_owner_entities`, `manage_asset_tags` | Properties, documents, deed import and their configuration |
| `manage_tenants` | Tenants |
| `manage_asset_rentals` | Agreements and contract import |
| `manage_rental_payments` | Payments, rent check, statements |
| `manage_asset_expenses` | Expenses |
| `view_reports` | Reports and CSV export |
| `manage_fx_rates` | Base currency and FX rates |
| `manage_users`, `manage_permission_sets`, `manage_smtp_settings`, `manage_portal_settings`, `manage_audit_logs` | Administration |

---

## Ideas not built

- Tenant-facing receipts or statements.
- Import of historical payments and expenses from CSV.
- Valuation history and equity tracking.

---

Private / internal use. Author: Chrysanthos Kattimeris.
