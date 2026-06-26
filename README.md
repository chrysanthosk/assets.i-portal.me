# assets.i-portal.me

A **Laravel 12 + AdminLTE 4 (Bootstrap 5)** property/real-estate portfolio manager:
track owned assets, tenants, rental agreements & payments, expenses, documents,
and profit/loss reporting — with roles & permissions, 2FA, and audit logging.

---

## Features

**Platform**
- Username + password authentication, 2FA (Google Authenticator) with recovery codes
- Role & permission management (Spatie) + per-route permission gates
- User & profile management (name, email with OTP confirmation)
- Password strength meter (zxcvbn), SMTP configuration & test email
- Audit logging across all mutations
- Dark / Light mode, security headers, encrypted sessions

**Asset & rentals modules**
- **Assets** — properties with purchase, financing, title-deed, location & physical details, tags, documents
- **Tenants** — first-class tenant records linked to rental agreements
- **Rental income** — agreements per asset (currency, period, active status)
- **Rental payments** — record/schedule payments, track **arrears & overdue**
- **Expenses** — categorised property costs (maintenance, tax, insurance, …)
- **Reports** — per-asset & portfolio **P&L** with **CSV export**, consolidated to a base currency via **FX rates**
- **Document lifecycle** — type classification + **expiry reminders** (insurance/certs)
- **Dashboard** — totals, monthly income, occupancy, outstanding payments, document-expiry reminders

---

## Requirements

### Docker install (recommended)
- Docker Engine **24+**
- Docker Compose **v2**

> MySQL, PHP, Nginx and Node are all provided by the containers — nothing else to install.

### Regular (bare-metal) install
- PHP **8.4+**
- Composer
- Node.js **18+**
- npm
- MySQL **8.0+**
- Git

---

## Installation

There are two ways to install assets.i-portal.me. The interactive `scripts/install.sh`
asks which one you want; you can also follow the manual steps below.

```bash
git clone git@github.com:chrysanthosk/assets.i-portal.me.git
cd assets.i-portal.me
sudo ./scripts/install.sh     # choose: 1) Regular  or  2) Docker
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
1. creates `.env` (if missing) and generates `APP_KEY`,
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

To redeploy after pulling new code you can also run
`./scripts/new_deploy.sh` and pick the **Docker** option.

---

## Option B — Regular (manual) install

### 1. Install PHP dependencies
```bash
composer install
```

### 2. Install Node dependencies
```bash
npm install
```

### 3. Environment configuration
```bash
cp .env.example .env
php artisan key:generate
```

### 4. MySQL configuration

Create a MySQL database:

```sql
CREATE DATABASE assets CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Update your `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=assets
DB_USERNAME=your_mysql_user
DB_PASSWORD=your_mysql_password
```

### 5. Run migrations & seed initial data
```bash
php artisan migrate
php artisan db:seed --class=PortalBootstrapSeeder
```

#### Default admin credentials
```
Username: admin@example.com
Password: ChangeMe123!
```

> Login is by the **username** field. The bare-metal seeder sets the username
> equal to the email (`admin@example.com`); the Docker bootstrap uses `admin`.
> **Important:** Change this password immediately after first login.

### 6. Build frontend assets
```bash
npm run build      # production
npm run dev        # development (hot reload)
```

### 7. Start the application
```bash
php artisan serve
```

Open:
```
http://127.0.0.1:8000
```

> For automated production provisioning (Nginx vhost, SSL, system user, DB user),
> use `sudo ./scripts/install.sh` and choose **Regular**.

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

## Testing

```bash
# Unit/feature tests (PHPUnit, in-memory SQLite)
php artisan test
./vendor/bin/pint --test          # code style (also enforced by CI)
```

**End-to-end smoke tests** (`scripts/e2e/`) drive the running Docker stack over
HTTP to verify the main journeys — money flow (assets → tenants → agreements →
payments → expenses → FX → P&L + CSV), documents (type/expiry + reminders), and
ops (`/health`, backups, 2FA enforcement):

```bash
docker compose up -d --build
./scripts/e2e/run-all.sh          # BASE_URL / ADMIN_USER / ADMIN_PASS overridable
```

See [`scripts/e2e/README.md`](scripts/e2e/README.md) for details.

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
- **Database backups:** `./scripts/backup-db.sh` dumps the Dockerised MySQL to `backups/*.sql.gz` with retention (`RETENTION_DAYS`, default 14). Schedule it via cron.
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
- Rental payment schedules (auto-generate expected payments)
- Asset valuation history & equity tracking
- Multi-unit (building → units) hierarchy

---

## License

Private / Internal Use

---

## Author

**Chrysanthos Kattimeris**
