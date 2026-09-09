# CLAUDE.md

Guidance for Claude Code (and other AI assistants) when working in this repository.

---

## ⚠️ Working agreement (read first)

1. **Always create a new branch before making any changes.**
   ```bash
   git switch -c <type>/<short-description>      # feature/…, fix/…, chore/…
   ```
   Never commit directly onto `master`. One PR per task; the owner merges and deploys.
2. **Never delete or overwrite files you did not create** (especially untracked,
   git-ignored files such as `.env`, `docker-compose.override.yml`) during cleanup.
3. **Never run destructive DB commands** in production/Docker
   (`migrate:fresh`, `db:wipe`, `docker compose down -v`). Deploys are additive only.
4. Don't commit `.env` or any secrets. Never rotate `APP_KEY` on a live install:
   2FA secrets, recovery codes and the SMTP password are encrypted with it.
5. Before pushing: `./vendor/bin/pint --test`, `php artisan test`, `npm run build`.

---

## Project overview

**assets.i-portal.me** — a Laravel 13 + Bootstrap 5 property-portfolio manager for a
single owner, with a hand-written portal shell (no AdminLTE, no Tailwind, no Alpine).

Domain: **Properties** (assets) → **Agreements** (monthly or instalment schedules) →
**Payments** generated from them, confirmed through the **Rent check** loop (daily
digest email with signed Yes/No links) → **Expenses** → **Reports** (P&L in a base
currency via FX rates). **Tenants** come from agreements. Three document readers use
the Anthropic API: **title deeds** (Cyprus, Dubai) → property, **contracts** (English,
Greek) → agreement, **manager statements** → payment amount.

Platform: username/password auth (registration closed), 2FA with recovery codes,
email-change OTP, Spatie roles/permissions, SMTP settings in the UI, audit log,
"Advanced features" switch that hides the multi-user/bookkeeping modules.

### Stack
- PHP 8.5 in the image and CI (`composer.json` floor `^8.3`), Laravel 13, PHPUnit 13,
  Spatie Permission 8, Google2FA Laravel 3, `anthropic-ai/sdk`, Sentry (inert without DSN).
- MySQL (`mysql:latest`) in Docker; SQLite for local dev and tests.
- Node 22, Vite 8 (rolldown), Bootstrap 5.3, Bootstrap Icons, Inter (self-hosted via
  `@fontsource-variable/inter`), zxcvbn (lazy-loaded only on password pages).

---

## Repository layout

```
app/
  Console/Commands/      MakeAdminUser, GenerateDueRent (rent:generate-due),
                         SendRentReminders (rent:send-reminders), SendDocumentReminders
  Http/Controllers/      Assets, AssetImport (deed), AssetDocuments, AssetRentals (agreements),
                         AgreementImport (contract), RentalPayments (+ statements, adjust),
                         RentConfirmation (signed email links, no auth), Tenants, AssetExpenses,
                         Reports, Dashboard, Profile, TwoFactor, AuditLogs, Health,
                         Settings/{Portal, Smtp, Users, PermissionSets, AssetTypes, OwnerEntities, Currencies}
  Http/Requests/AssetRules.php   one rule set + normalize() for property create/update/import
  Http/Middleware/       EnsureTwoFactorIsVerified (+ admin enforcement), SecurityHeaders
  Models/                User, Asset, AssetType, OwnerEntity, AssetTag, AssetDocument, AssetRental,
                         Tenant, RentalPayment, AssetExpense, FxRate, PortalSetting, SmtpSetting,
                         AuditLog, DeedImport (kind = deed | agreement)
  Support/
    RentSchedule.php     generateDue / backfill / reconcile / sendReminders (digest) — the rent loop
    DocumentReminders.php  weekly expiry digest
    Fx.php               base currency, rates, currencies(), multiCurrencyInUse()
    Portal.php           advanced-mode switch (@advanced directive)
    MailConfig.php       applies the UI SMTP settings to the mailer (cached)
    Audit.php            audit-log helper
    Deeds/               ClaudeDocumentReader (shared API call), DeedSchema, DeedMapper, extractors
    Agreements/          AgreementSchema, AgreementMapper, extractor
    Statements/          StatementSchema, extractor
  Mail/                  RentCheckDigestMail, DocumentExpiryDigestMail, EmailChangeOtpMail
config/portal.php, portal_permissions.php, services.php (anthropic key/model)
database/migrations/   additive; keep them portable (SQLite runs the tests)
resources/views/       layouts/app (shell), components/stat, assets/*, payments/*, …
resources/css/app.css  theme tokens + shell; resources/js/app.js  sidebar/theme/modals
routes/web.php, auth.php, console.php (scheduler)
scripts/               install.sh, new_deploy.sh, docker-preflight.sh, backup.sh, e2e/ (Docker only)
docker/                entrypoint.sh, nginx/default.conf, php/php.ini, supervisor (fpm+nginx+queue+scheduler)
Dockerfile, docker-compose.yml, .env.docker.example (Docker), .env.example (local dev + container base)
tests/Feature/*        100 tests; extractors are faked via container bindings — never call the API
```

---

## Running it

**Docker (production):** `./scripts/install.sh` once; then `git pull --ff-only &&
./scripts/new_deploy.sh` (`--pull` also fetches MySQL). Both run
`scripts/docker-preflight.sh`: creates `.env`, persists `APP_KEY`, confirms
`APP_TIMEZONE` (prompt, default Europe/Athens, skipped without a TTY), frees `WEB_PORT`.
The app container runs Nginx + PHP-FPM + `queue:work` + `schedule:work` under Supervisor.
MySQL is not published on the host. Volumes `db_data` and `app_storage` persist.

**Local:** `composer install && npm install && cp .env.example .env && php artisan
key:generate && php artisan migrate && php artisan db:seed --class=PortalBootstrapSeeder
&& npm run build && php artisan serve`.

**Tests:** `php artisan test` (SQLite `:memory:`). `tests/TestCase` flushes the cache and
`Portal` static state between tests. The production image has no dev deps or tests.

Production host notes live outside the repo (server-local `docker-compose.override.yml`
attaches the app to the reverse proxy network).

---

## How the rent loop works (read before touching payments)

- `RentSchedule::generateDue($asOf)` runs daily at 06:00: for each active agreement covering
  the month it creates the missing `RentalPayment` keyed by `(asset_rental_id, period)`
  via `firstOrCreate`. Monthly: `period = YYYY-MM`, due on the agreement's `due_day` (else
  the portal default), shifted a month when `paid_in_arrears`, never before the agreement
  start. Instalments: `period = YYYY-MM#n` for each instalment whose month matches, with
  its `label`; amounts may be 0 (commission to fill in later).
- `backfill($rental)` walks from the start of the year (or the agreement start) to today;
  called on agreement create/update, contract import, and by the Rent check "Generate"
  button (`backfillAll`). `reconcile($rental, $old)` runs on update: pending generated rows
  are removed when the schedule changed or re-priced when only the monthly amount changed;
  confirmed, not-received, statement-backed or already-reminded rows are left alone.
- `sendReminders()` at 08:00 sends ONE `RentCheckDigestMail` listing every pending payment
  due today or earlier, when at least one is new or past `rent_reminder_repeat_days`. Each
  row carries `URL::temporarySignedRoute('rent.confirm', …)` Yes/No links (60 days). The
  landing page is GET (no side effect) and applies on POST — mail scanners must not confirm.
- Statuses: `pending` → `paid` (markReceived) or `not_received` (markNotReceived, clears
  `paid_date`). Overdue = not_received, or pending past due. `daysLate()` is whole days.
- `payments.adjust` corrects amount/currency (optionally marking received);
  `payments.statement` / `payments.statementForAsset` read a manager statement and open the
  same dialog with the net payable proposed; the PDF is filed as a `Statement` document.

---

## Conventions & gotchas

- **Naming in the UI**: Properties (not assets), Agreements, Tenants, Rent check,
  sentence case labels. Code keeps the `Asset*` class names.
- **Permissions**: routes use `permission:<name>` middleware; names in
  `config/portal_permissions.php`; after changes run `PortalPermissionsSeeder` and
  `permission:cache-reset`. Spatie 8 throws when a role is missing — guard `User::role()`.
- **Advanced mode**: wrap admin/bookkeeping markup in `@advanced … @endadvanced`. Routes stay
  permission-gated and reachable; only navigation and form fields are hidden. Currencies & FX
  also shows when `Fx::multiCurrencyInUse()`.
- **Money**: every amount carries its own currency. Sum across currencies only through
  `Fx::toBase()`; call `Fx::flush()` after changing rates/base (also clears the cached
  multi-currency flag). Currency selects use `Fx::currencies()`.
- **Settings**: `PortalSetting::get/set/name()` (cached 5 min, `set()` invalidates).
  SMTP settings are applied by `MailConfig::apply()` on boot (cached; `MailConfig::forget()`
  on save). Test emails go through the same mailer.
- **Document readers**: `ClaudeDocumentReader::read()` + a `*Schema::schema()` +
  `normalize()`. Schemas must contain **no union types** (Anthropic caps them at 16): every
  scalar is a string, `""` = unknown, numbers as digits; `normalize()` types them. Default
  model `claude-opus-5` (`ANTHROPIC_MODEL`). Key: `PortalSetting anthropic_api_key`
  (encrypted) or `ANTHROPIC_API_KEY`. Extractors are bound in `AppServiceProvider`; tests
  bind fakes. `DeedImport.kind` separates deed and agreement imports — guard it.
- **Uploads** go to the private `local` disk under `assets/{id}/`; downloads are streamed
  by `AssetDocumentsController@download` after checking `asset_id`. Deleting a property
  deletes its files.
- **Layout**: `.shell`, `.sidebar` (`.sb-link`, `.sb-header`, `.sb-sub`), `.topbar`,
  `.content`, `<x-stat>` tiles (`sub` is escaped, `sub-html` is not), `.feed-row`.
  Theme tokens on `:root` / `[data-bs-theme="dark"]`; theme applied before first paint from
  localStorage. Page titles: `@section('title')` or the route-name map in `layouts/app`.
  Mobile: sidebar off-canvas under 992px; tables inside `.table-responsive`; card headers
  `d-flex flex-wrap`; flex children that must shrink need `.min-w-0` (defined in app.css,
  Bootstrap has none). Never nest a `<form>` inside `<tr>` — use the `form=` attribute.
  Pagination uses the Bootstrap 5 view (`Paginator::useBootstrapFive()`).
- **Migrations must be portable**: guard MySQL-only DDL behind a driver check; drop indexes
  before dropping indexed columns (SQLite).
- **Trusted proxies**: private ranges are trusted in `bootstrap/app.php` so HTTPS is detected
  behind the reverse proxy; `APP_FORCE_ROOT_URL` keeps generated URLs on `APP_URL`.
- Keep code Pint-clean; CI runs `pint --test`, the Vite build and PHPUnit on PHP 8.5 / Node 22.
