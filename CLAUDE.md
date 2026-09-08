# CLAUDE.md

Guidance for Claude Code (and other AI assistants) when working in this repository.

---

## ⚠️ Working agreement (read first)

1. **Always create a new branch before making any changes.**
   Before editing files for a new task, create a dedicated branch off the current
   branch:
   ```bash
   git switch -c <type>/<short-description>      # e.g. feature/dockerise, fix/login-redirect
   ```
   Never commit new work directly onto `master` or a shared feature branch.
2. **Never delete or overwrite files you did not create** (especially untracked,
   git-ignored files such as `.env`) during cleanup. Back up first and confirm.
3. **Never run destructive DB commands** in production/Docker
   (`migrate:fresh`, `db:wipe`, `docker compose down -v`). Deploys are additive only.
4. Don't commit `.env` or any secrets.

---

## Project overview

**assets.i-portal.me** — a Laravel 12 + Bootstrap 5 property/real-estate
portfolio manager with a custom portal shell (no AdminLTE).

Platform: username/password auth, 2FA (Google Authenticator) with recovery codes,
Spatie roles & permissions, profile management with email OTP, zxcvbn meter, SMTP
config + test email, audit logging, dark/light theme.

Domain modules: **Assets**, **Tenants**, **Rental income** (agreements),
**Rental payments** (arrears/overdue), **Expenses**, **Reports** (per-asset &
portfolio P&L + CSV export), **Currencies & FX** (base currency + rates),
**Document lifecycle** (type + expiry reminders), **Rent check** (auto-generated
monthly payments + email confirmation loop), **Title deed import** (upload a scanned
Cyprus Land Registry sheet → Claude extracts the fields → review → asset + document).
The dashboard surfaces income, occupancy, outstanding/unconfirmed payments, and
document-expiry reminders.

### Stack
- **PHP 8.4+** (the `composer.lock` resolves dependencies that require ≥ 8.4)
- **Laravel 12**, Composer
- **MySQL 8+** in production / **`mysql:latest`** in Docker (SQLite is the default
  for local quick experiments via `.env.example`, and is used by the test suite)
- **Node 18+ / npm**, **Vite 7**, Bootstrap 5, Bootstrap Icons, Inter (self-hosted via
  `@fontsource-variable/inter`). No Tailwind/Alpine/AdminLTE — the shell (sidebar, topbar,
  theme tokens, stat tiles) is hand-written in `resources/css/app.css` + `resources/js/app.js`.
- Key packages: `spatie/laravel-permission`, `pragmarx/google2fa-laravel`,
  `sentry/sentry-laravel` (optional, inert without a DSN)

---

## Repository layout

```
app/
  Console/Commands/MakeAdminUser.php   # `php artisan make:admin` — creates/updates an Admin
  Http/Controllers/                    # Assets, Tenants, AssetRentals, RentalPayments,
                                       #   AssetExpenses, Reports, Health, Dashboard, TwoFactor,
                                       #   Settings/* (Users, Smtp, Currencies, AssetTypes, …)
  Http/Middleware/                     # EnsureTwoFactorIsVerified (+ admin enforce), SecurityHeaders
  Models/                              # User, Asset, AssetType, OwnerEntity, AssetTag, AssetDocument,
                                       #   AssetRental, Tenant, RentalPayment, AssetExpense, FxRate,
                                       #   PortalSetting, SmtpSetting, AuditLog
  Support/                             # Audit (audit-log helper), Fx (currency conversion),
                                       #   RentSchedule (monthly payments + reminders), DocumentReminders
                                       #   (weekly expiry digest), MailConfig, Portal (advanced switch),
                                       #   Deeds/ (DeedExtractor interface, ClaudeDeedExtractor, DeedSchema, DeedMapper)
  Listeners/ Mail/ Providers/ View/
config/
  permission.php, portal_permissions.php   # permission registry used by seeders
  portal.php                               # REQUIRE_2FA_FOR_ADMINS toggle + admin role
  sentry.php                               # error tracking (DSN via SENTRY_LARAVEL_DSN)
database/
  migrations/                          # users, cache, jobs, permissions, portal_settings, assets*
  seeders/
    PortalPermissionsSeeder.php        # idempotent roles+permissions sync (use this in deploys)
    PortalBootstrapSeeder.php          # permissions + a default admin user
    DatabaseSeeder.php                 # calls the above + creates admin@example.com
    AssetTypeSeeder.php, OwnerEntitySeeder.php
routes/  web.php  auth.php  console.php
resources/  css/app.css  js/app.js  views/
.github/workflows/ci.yml          # CI: Pint (lint) + Vite build + PHPUnit on SQLite
scripts/                             # Docker only — there is no bare-metal path
  install.sh       # first install: asks a few questions, writes .env, builds + starts the stack
  new_deploy.sh    # update: preflight → docker compose up -d --build (volumes preserved); --pull
  docker-preflight.sh  # .env guard: creates .env, persists APP_KEY, resolves WEB_PORT clashes
  backup.sh        # DB dump + storage archive with retention; --install-cron writes /etc/cron.d
docker/
  entrypoint.sh                 # app container bootstrap (key, wait-for-db, migrate, seed, optimize)
  nginx/default.conf            # Nginx vhost (root = public/, fastcgi -> 127.0.0.1:9000)
  php/php.ini                   # runtime PHP/OPcache settings
  supervisor/supervisord.conf   # runs php-fpm + nginx + queue worker in the app container
Dockerfile                      # 3-stage: node assets -> composer vendor -> php:8.4-fpm runtime
docker-compose.yml              # services: app (web+fpm+queue), db (mysql:latest)
.dockerignore
.env.example                    # local-dev template (SQLite default)
.env.docker.example             # Docker template (copy to .env for docker compose)
```

---

## Running the project

### Docker (recommended)
```bash
cp .env.docker.example .env        # set DB_PASSWORD / DB_ROOT_PASSWORD / WEB_PORT
docker compose up -d --build       # http://localhost:8080 (WEB_PORT)
```
The `app` container entrypoint auto-runs: create `.env` + `APP_KEY`, wait for DB,
`migrate --force`, seed `PortalPermissionsSeeder`, optional admin bootstrap
(`ADMIN_EMAIL`/`ADMIN_PASSWORD`), then `php artisan optimize`.

Architecture notes:
- One `app` image runs **Nginx + PHP-FPM + the queue worker** via Supervisor, so
  all share the same code, `.env`, and `APP_KEY`. Do **not** split the queue into a
  separate container that bypasses the entrypoint — it would lack `.env`/`APP_KEY`.
- The entrypoint bootstraps with `CACHE_STORE=file`/`SESSION_DRIVER=file` (because
  the `cache`/`sessions` tables don't exist yet), then restores the database
  drivers before launching. Preserve this ordering.
- **MySQL data lives in the `db_data` named volume and persists across
  `docker compose up -d --build`.** Migrations are additive (`migrate --force`).
  Only `docker compose down -v` wipes the database.

### Local development (no Docker)
```bash
composer install && npm install
cp .env.example .env && php artisan key:generate     # SQLite by default
php artisan migrate && php artisan db:seed --class=PortalBootstrapSeeder
npm run build          # or: npm run dev
php artisan serve
```

### Automated provisioning (Docker only)
- `sudo ./scripts/install.sh` — first install: writes `.env`, builds, starts.
- `./scripts/new_deploy.sh [--pull]` — update after `git pull` (volumes preserved).
  Non-interactive: no menu, no prompts.
- Both run `scripts/docker-preflight.sh` first. It creates `.env`
  from `.env.docker.example` (random DB passwords), generates `APP_KEY` once and
  keeps it in the host `.env` (passed through `docker-compose.yml` — never rotate
  it, 2FA secrets are encrypted with it), and moves `WEB_PORT` to the next free
  host port when another stack already listens there (a localhost `APP_URL`
  follows the port; a custom one is left alone with a warning). MySQL is not
  published on the host; add a `db: ports:` entry to a local
  `docker-compose.override.yml` only when a DB tool needs it.

---

## Common commands

| Task | Local dev | Docker |
|------|-----------|--------|
| Artisan | `php artisan <cmd>` | `docker compose exec app php artisan <cmd>` |
| Create admin | `php artisan make:admin` | `docker compose exec app php artisan make:admin` |
| Migrate | `php artisan migrate` | runs automatically on container start |
| Tests | `php artisan test` | `docker compose exec app php artisan test` |
| Build assets | `npm run build` | baked into the image at build time |
| Logs | `storage/logs/laravel.log` | `docker compose logs -f app` |
| Stop / remove | — | `docker compose down` (add `-v` to also delete the database — never in production) |

**Default login (Docker, from `.env.docker.example`):** username **`admin`** /
password **`ChangeMe123!`**. Authentication is by the **`username`** column, **not
email** — a common gotcha. Configure via `ADMIN_USERNAME` / `ADMIN_EMAIL` /
`ADMIN_PASSWORD`. **Change the password immediately after first login.**

**Port control (Docker):** set `WEB_PORT` in `.env`; `APP_URL` derives from it.
`APP_FORCE_ROOT_URL=true` (set in `docker-compose.yml`) makes Laravel generate
URLs from `APP_URL` so the published port survives the proxy/port mapping —
otherwise redirects drop the port (nginx listens on `:80` inside the container).

---

## Conventions & gotchas

- **PHP version**: target **8.4+** for Docker images and CI — 8.3 fails the
  Composer platform check baked into `vendor/composer/platform_check.php`.
- **Property form validation** lives once in `App\Http\Requests\AssetRules` (rules +
  normalize) and is used by create, update and the deed-import confirm.
- **Portal settings are cached** (`PortalSetting::get/name`, 5 min, invalidated by
  `set()`); tests flush the cache in `TestCase::setUp`.
- **Permissions**: routes are guarded by `permission:<name>` middleware; permission
  names live in `config/portal_permissions.php`. After changing them, run
  `PortalPermissionsSeeder` and `php artisan permission:cache-reset`.
- **Seeders are idempotent** (`firstOrCreate` / `syncPermissions`) — safe to re-run.
- **Schema is FK-based**: assets use `asset_type_id` / `owner_entity_id` (the legacy
  `type` / `owner_entity` strings were dropped); documents use `path` / `mime_type` /
  `size_bytes` (legacy `file_path` / `mime` / `size` dropped). Read via relationships.
- **Currency**: amounts carry their own `currency`; consolidate via `App\Support\Fx`
  (base currency in `portal_settings`, rates in `fx_rates`). Call `Fx::flush()` in
  tests that change rates/base.
- **Migrations must be portable** (the suite runs on SQLite): guard MySQL-only DDL
  (e.g. `information_schema`, `ALTER … ADD FOREIGN KEY`) behind a driver check.
- **CI runs `pint --test`** — keep code Pint-clean (`./vendor/bin/pint` before commit).
- **Tests**: `tests/Feature` + `tests/Unit` (PHPUnit, SQLite `:memory:`). Run `php artisan test`.
- **Advanced mode** (`App\Support\Portal::advanced()`, Settings → Portal, off by
  default) gates the multi-user/bookkeeping UI: users, permission sets, owner
  entities, tags, currencies & FX, audit log, plus the owner-entity/tag fields on
  asset forms. Wrap such markup in `@advanced … @endadvanced`. Routes stay
  permission-gated and reachable; only navigation and form fields are hidden.
- **Document reading (deeds + manager statements)** goes through
  `App\Support\Deeds\ClaudeDocumentReader` (Anthropic SDK, vision + structured JSON).
  `ClaudeDeedExtractor`/`DeedSchema` cover Cyprus and Dubai (DIFC/DLD) deeds;
  `Statements\ClaudeStatementExtractor`/`StatementSchema` read monthly owner statements
  (gross, fee, net payable) to correct a payment's amount (`payments.statement` →
  session → adjust modal → `payments.adjust`). Schemas must stay union-free.
  The key is stored encrypted in `portal_settings` (Settings → Portal) with
  `ANTHROPIC_API_KEY` as env fallback; model from `ANTHROPIC_MODEL` (default
  `claude-opus-5`). Tests bind a fake `DeedExtractor` — never call the API in tests.
- Don't introduce a new framework without need; the front-end is **Bootstrap 5 only**
  (no Tailwind/Alpine/AdminLTE). Layout classes: `.shell`, `.sidebar` (`.sb-link`,
  `.sb-header`, `.sb-sub`), `.topbar`, `.content`, `.stat` tiles (`<x-stat>`), `.feed-row`.
  Light/dark come from CSS tokens on `:root` / `[data-bs-theme="dark"]`; the theme is
  persisted in localStorage and applied before first paint. Page titles come from
  `@section('title')` or the route-name map in `layouts/app.blade.php`. Mobile: the sidebar
  is off-canvas under 992px (hamburger in the topbar), tables must sit in `.table-responsive`,
  card headers use `d-flex flex-wrap`. Match the existing Laravel idioms and surrounding style.
