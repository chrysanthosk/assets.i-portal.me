# assets.i-portal.me

A **Laravel 12 + Bootstrap 5** property/real-estate portfolio manager with a modern,
mobile-friendly portal shell (dark/light):
track owned assets, tenants, rental agreements & payments, expenses, documents,
and profit/loss reporting — with roles & permissions, 2FA, and audit logging.

---

## Features

**Platform**
- Username + password authentication, 2FA (Google Authenticator) with recovery codes
- Role & permission management (Spatie) + per-route permission gates
- User & profile management (name, email with OTP confirmation)
- Password strength meter (zxcvbn), SMTP configuration & test email
- **Simple by default**: users, permission sets, owner entities, tags, currencies & FX and the audit log
  are hidden until "Advanced features" is switched on under Settings → Portal
- Audit logging across all mutations
- Dark / Light mode, security headers, encrypted sessions

**Asset & rentals modules**
- **Assets** — properties with purchase, financing, title-deed, location & physical details, tags, documents
- **Title deed import** — upload a scanned Cyprus Land Registry sheet or a Dubai (DIFC/DLD) title deed (PDF/photo); Claude reads the
  registration number, location, plot reference, owners & share, areas and valuations; you review a
  prefilled form and the property is created with the scan attached as its title-deed document
  (needs an Anthropic API key under Settings → Portal, or `ANTHROPIC_API_KEY`)
- **Tenants** — first-class tenant records linked to rental agreements
- **Agreements** — per tenancy or management contract: monthly rent (in advance or in arrears) or a
  set of dated instalments that repeats every contract year (e.g. an annual guarantee paid 15 % on
  15 Apr, 15 % on 31 May …). **Import a contract PDF** and the parties, period and schedule are read
  for you; the contract is filed with the property's documents
- **Rental payments** — record/schedule payments, track **arrears & overdue**
- **Rent check** — expected payments are generated monthly from active agreements; one daily email
  lists every payment waiting for an answer with Yes/No links per row, repeating until answered. For variable
  rent (short-let operators) correct the amount, or upload the manager's monthly statement and the
  net payout is read from it and filed with the property's documents
- **Expenses** — categorised property costs (maintenance, tax, insurance, …)
- **Reports** — per-asset & portfolio **P&L** with **CSV export**, consolidated to a base currency via **FX rates**
- **Document lifecycle** — type classification + **expiry reminders** (dashboard, bell, and a
  Monday email digest of expired / soon-expiring documents)
- **Dashboard** — totals, monthly income, occupancy, outstanding payments, document-expiry reminders

---

## Requirements

### Docker install (recommended)
- Docker Engine **24+**
- Docker Compose **v2**

> MySQL, PHP, Nginx and Node are all provided by the containers — nothing else to install.

### Local development (optional, no Docker)
- PHP **8.4+**, Composer, Node.js **18+** / npm (SQLite is used by default)

---

## Installation

Production runs in Docker. The installer asks a few questions, writes `.env`,
builds the image and starts the stack:

```bash
git clone git@github.com:chrysanthosk/assets.i-portal.me.git
cd assets.i-portal.me
sudo ./scripts/install.sh
```

---

## Option A — Docker (recommended)

A multi-container stack is provided:

| Service | Description |
|---------|-------------|
| `app`   | Laravel application — **Nginx + PHP-FPM + queue worker** in one image (managed by Supervisor) |
| `db`    | **MySQL (latest)** with a persistent named volume |

### Quick start

```bash
git clone git@github.com:chrysanthosk/assets.i-portal.me.git
cd assets.i-portal.me

cp .env.docker.example .env       # edit DB_PASSWORD / DB_ROOT_PASSWORD / WEB_PORT
docker compose up -d --build
```

The application is served at **http://localhost:8080**.

> **Port control:** the published port is set by **`WEB_PORT`** in `.env`
> (default `8080`). `APP_URL` is derived from it automatically — change only
> `WEB_PORT` and re-run `docker compose up -d`.

### Default login

With the values from `.env.docker.example`, an admin is created on first boot:

| | |
|---|---|
| **Username** | `admin`  ← log in with this, **not** the email |
| Password | `ChangeMe123!` |

> Authentication is by **username**, not email. Change the password immediately
> after first login. Configure these via `ADMIN_USERNAME` / `ADMIN_PASSWORD` /
> `ADMIN_EMAIL` in `.env`.

On first boot the `app` container automatically:
1. creates its own `.env` (if missing) and uses the `APP_KEY` from the host
   `.env` (generating a throwaway one only if none is provided),
2. waits for MySQL to become healthy,
3. runs `php artisan migrate --force` (**additive — never drops data**),
4. seeds roles & permissions (idempotent `PortalPermissionsSeeder`),
5. optionally creates an admin user if `ADMIN_EMAIL` / `ADMIN_PASSWORD` are set,
6. runs `php artisan optimize`.

### Database persistence

The MySQL data lives in the **`db_data`** named volume and is **preserved across
rebuilds and redeploys** (`docker compose up -d --build`). Migrations are always
additive (`migrate --force`, never `migrate:fresh`), so **your database is never
dropped on deployment.** It is only removed if you explicitly run
`docker compose down -v`.

### Create / manage the admin user

If you did not set `ADMIN_EMAIL` / `ADMIN_PASSWORD`, create an admin manually:

```bash
docker compose exec app php artisan make:admin
```

### Common Docker commands

```bash
docker compose ps                       # status
docker compose logs -f app              # follow app logs
docker compose exec app php artisan ... # run any artisan command
docker compose pull db                  # pull the latest MySQL image
docker compose up -d --build            # redeploy after code changes (data kept)
docker compose down                     # stop (data kept)
docker compose down -v                  # stop AND delete the database volume
```

To redeploy after pulling new code run `./scripts/new_deploy.sh` (add `--pull`
to also fetch the latest MySQL image). It is non-interactive. Before rebuilding
it runs `scripts/docker-preflight.sh`, which:

- creates `.env` from `.env.docker.example` if it is missing (with random DB
  passwords),
- generates `APP_KEY` once and stores it in the host `.env` so it survives
  rebuilds (2FA secrets are encrypted with it — never rotate it on a live
  install),
- moves `WEB_PORT` to the next free host port when another process or stack
  already listens there. A `localhost` `APP_URL` follows the new port; a custom
  `APP_URL` is left untouched with a warning so you can update your reverse
  proxy target. MySQL is never published on the host.

The preflight is idempotent and can be run on its own:
`./scripts/docker-preflight.sh`.

---

## Option B — Local development (no Docker)

For hacking on the code with SQLite:

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate && php artisan db:seed --class=PortalBootstrapSeeder
npm run dev            # or: npm run build
php artisan serve      # http://127.0.0.1:8000 — login admin / (see seeder output)
```

There is no supported bare-metal production install; deploy with Docker.

---

## SMTP Configuration

Supported encryption modes:
- **TLS (STARTTLS)** — Port **587**
- **SSL (SMTPS)** — Port **465**

Invalid combinations (e.g. SSL + 587) are blocked.

### SendGrid example
```
Host: smtp.sendgrid.net
Port: 587
Encryption: tls
Username: apikey
Password: <SENDGRID_API_KEY>
```

Use the **Test Email** button to verify connectivity.

---

## Two-Factor Authentication (2FA)

- Google Authenticator compatible
- QR code + manual secret
- Enforced via middleware
- Challenge page on login if enabled

---

## Password Strength

- Powered by `zxcvbn`
- Visual strength meter
- Used on:
    - Profile password change
    - User create
    - User edit

---

## Useful Commands

```bash
php artisan optimize:clear
php artisan route:list
php artisan migrate:fresh --seed
npm run build
```

With Docker, prefix artisan/npm commands with `docker compose exec app`:

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan route:list
docker compose exec app php artisan make:admin
```

> ⚠️ Avoid `migrate:fresh` / `migrate:fresh --seed` in production / Docker — it
> **drops all tables**. Normal deploys use additive `migrate --force`.

---

## Permissions

Access is gated per route by Spatie permissions (synced from
`config/portal_permissions.php` via `PortalPermissionsSeeder`). Notable ones:

| Permission | Grants |
|------------|--------|
| `manage_assets`, `manage_asset_tags`, `manage_asset_types`, `manage_owner_entities` | Assets & their configuration |
| `manage_tenants` | Tenants |
| `manage_asset_rentals` | Rental agreements |
| `manage_rental_payments` | Rental payments & arrears |
| `manage_asset_expenses` | Expenses |
| `view_reports` | P&L reports + CSV export |
| `manage_fx_rates` | Base currency & FX rates |
| `manage_users`, `manage_permission_sets`, `manage_smtp_settings`, `manage_portal_settings`, `manage_audit_logs` | Administration |

Admins get all permissions. After changing the registry, run
`php artisan db:seed --class=PortalPermissionsSeeder` (the Docker entrypoint does this automatically).

---

## Operations & observability

- **Health check:** `GET /health` (unauthenticated) returns `200` + DB status, `503` if the database is unreachable. Laravel's `/up` is also available.
- **Backups:** `./scripts/backup.sh` dumps MySQL to `backups/<db>-<stamp>.sql.gz` **and**
  archives uploaded files (`storage/app`, i.e. title deeds and documents) to
  `backups/storage-<stamp>.tar.gz`, keeping `RETENTION_DAYS` (default 14). Set
  `BACKUP_REMOTE=user@host:/path` in `.env` to rsync each new archive off the server.
  Install the nightly job with `sudo ./scripts/backup.sh --install-cron 02:30`
  (writes `/etc/cron.d/assets-backup`, log in `backups/backup.log`).
  Restore: `gunzip -c backups/assets-*.sql.gz | docker compose exec -T db mysql -uroot -p<root> assets`
  and `docker compose exec -T app tar -xzf - -C /var/www/html/storage < backups/storage-*.tar.gz`.
- **Logging:** in Docker, the app logs to **stderr** (`docker compose logs -f app`).
- **Error tracking (optional):** set `SENTRY_LARAVEL_DSN` in `.env` to enable Sentry; blank = disabled.
- **Enforce 2FA for admins (optional):** set `REQUIRE_2FA_FOR_ADMINS=true` to require admins / user-managers to enroll in 2FA before using the app.
- **CI:** `.github/workflows/ci.yml` runs Pint (lint), the Vite build, and PHPUnit on every push/PR.

---

## Security Notes

- Change the default admin password immediately
- Do not commit `.env`
- Use HTTPS in production (security headers + HSTS are applied automatically)
- Enable 2FA for admin accounts (optionally enforce via `REQUIRE_2FA_FOR_ADMINS`)
- Use strong SMTP credentials (SMTP passwords are stored encrypted)
- Sessions are encrypted (`SESSION_ENCRYPT=true`)

---

## Roadmap

- API authentication / public API
- Webhooks & notifications (e.g. overdue-payment / document-expiry alerts)
- Asset valuation history & equity tracking
- Multi-unit (building → units) hierarchy

---

## License

Private / Internal Use

---

## Author

**Chrysanthos Kattimeris**
