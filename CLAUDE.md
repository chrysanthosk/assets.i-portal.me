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

**assets.i-portal.me** — a Laravel 12 + AdminLTE 4 (Bootstrap 5) admin portal.

Features: username/password auth, Spatie roles & permissions, 2FA (Google
Authenticator), profile management with email OTP, password strength meter
(zxcvbn), SMTP configuration + test email, an assets/rentals module, audit logs,
and a dark/light theme toggle.

### Stack
- **PHP 8.4+** (the `composer.lock` resolves dependencies that require ≥ 8.4)
- **Laravel 12**, Composer
- **MySQL 8+** in production / **`mysql:latest`** in Docker (SQLite is the default
  for local quick experiments via `.env.example`)
- **Node 18+ / npm**, **Vite 7**, Tailwind 3, Bootstrap 5, AdminLTE 4
- Key packages: `spatie/laravel-permission`, `pragmarx/google2fa-laravel`

---

## Repository layout

```
app/
  Console/Commands/MakeAdminUser.php   # `php artisan make:admin` — creates/updates an Admin
  Http/                                # Controllers, middleware (auth, 2fa, permission:*)
  Models/                              # User, Asset*, OwnerEntity, AuditLog, PortalSetting, SmtpSetting
  Listeners/ Mail/ Providers/ Support/ View/
config/
  permission.php, portal_permissions.php   # permission registry used by seeders
database/
  migrations/                          # users, cache, jobs, permissions, portal_settings, assets*
  seeders/
    PortalPermissionsSeeder.php        # idempotent roles+permissions sync (use this in deploys)
    PortalBootstrapSeeder.php          # permissions + a default admin user
    DatabaseSeeder.php                 # calls the above + creates admin@example.com
    AssetTypeSeeder.php, OwnerEntitySeeder.php
routes/  web.php  auth.php  console.php
resources/  css/app.css  js/app.js  views/
scripts/
  install.sh       # interactive installer — asks: 1) Regular (bare-metal)  2) Docker
  new_deploy.sh    # interactive deploy/update — asks: 1) Regular  2) Docker
  uninstall.sh
docker/
  entrypoint.sh                 # app container bootstrap (key, wait-for-db, migrate, seed, optimize)
  nginx/default.conf            # Nginx vhost (root = public/, fastcgi -> 127.0.0.1:9000)
  php/php.ini                   # runtime PHP/OPcache settings
  supervisor/supervisord.conf   # runs php-fpm + nginx + queue worker in the app container
Dockerfile                      # 3-stage: node assets -> composer vendor -> php:8.4-fpm runtime
docker-compose.yml              # services: app (web+fpm+queue), db (mysql:latest)
.dockerignore
.env.example                    # bare-metal/local template (SQLite default)
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

### Bare-metal (manual)
```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
# configure MySQL in .env, then:
php artisan migrate
php artisan db:seed --class=PortalBootstrapSeeder
npm run build          # or: npm run dev
php artisan serve
```

### Automated provisioning
- `sudo ./scripts/install.sh` → choose **Regular** (installs Nginx/PHP-FPM/MySQL,
  creates system + DB user, Nginx vhost, optional Let's Encrypt) or **Docker**
  (pulls latest MySQL image, writes `.env`, `docker compose up -d --build`).
- `./scripts/new_deploy.sh` → choose **Regular** or **Docker** to update an
  existing deployment (DB volume preserved in Docker mode).

---

## Common commands

| Task | Bare-metal | Docker |
|------|------------|--------|
| Artisan | `php artisan <cmd>` | `docker compose exec app php artisan <cmd>` |
| Create admin | `php artisan make:admin` | `docker compose exec app php artisan make:admin` |
| Migrate | `php artisan migrate` | runs automatically on container start |
| Tests | `php artisan test` | `docker compose exec app php artisan test` |
| Build assets | `npm run build` | baked into the image at build time |
| Logs | `storage/logs/laravel.log` | `docker compose logs -f app` |

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
- **Permissions**: routes are guarded by `permission:<name>` middleware; permission
  names live in `config/portal_permissions.php`. After changing them, run
  `PortalPermissionsSeeder` and `php artisan permission:cache-reset`.
- **Seeders are idempotent** (`firstOrCreate` / `syncPermissions`) — safe to re-run.
- **Tests**: `tests/Feature` + `tests/Unit` (PHPUnit). Run `php artisan test`.
- Lint/format: `./vendor/bin/pint`.
- Don't introduce a non-Anthropic provider or new framework without need; match the
  existing Laravel idioms and the surrounding code style.
