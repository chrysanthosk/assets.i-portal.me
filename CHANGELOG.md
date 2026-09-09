# Changelog

Notable changes to assets.i-portal.me, newest first. Dates are merge dates; numbers are
GitHub pull requests. Add an entry to **Unreleased** with every PR.

## Unreleased

- CI: second job runs the migrations (including a rollback and re-run) and the whole
  test suite on MySQL, the production engine; the first job stays on SQLite.
- 2FA setup: the QR code is rendered on the server as inline SVG. The secret no longer
  leaves the server (previously an external QR image service received it).
- This changelog.

## 2026-09-09

- **Full review** (#49): agreement edits reconcile pending generated payments; base
  currency and FX caches invalidate on save; statement upload on instalment agreements
  attaches to the right instalment; last Admin cannot be deleted or demoted; email-change
  OTP throttled; deleting a property removes its files; trusted proxies for HTTPS behind
  the reverse proxy; nested table-row forms replaced; wording, wrapping and accessibility
  fixes; README and CLAUDE.md rewritten.
- **Rent check digest** (#48): one daily email listing every payment waiting for an
  answer, Yes/No links per row, instead of one email per payment.
- **Per-agreement due day, Greek leases** (#47): each agreement has its own due day; the
  contract reader handles Greek leases and transliterates names and places; the first
  monthly payment is never due before the agreement starts.
- Bootstrap 5 pagination; sidebar cannot scroll sideways (#46).

## 2026-09-08

- Mobile: feed rows wrap on phones, whole-day lateness, full-width stat tiles,
  Outstanding shown as one converted total (#45, #44).
- Currency selects come from settings and in-use currencies, including AED (#43).
- Tenants: **Fill from contracts** links agreements to tenant records and reads contact
  details from the filed contracts (#42); a typed tenant name creates the tenant (#41).
- Dashboard: rent received this year, collection rate, net for the year (#40).
- Payments already due this year are backfilled when an agreement is created or saved;
  the Rent check "Generate" button catches up missing ones (#39).
- **Instalment schedules and contract import** (#38): agreements can be paid monthly (in
  advance or the month after) or in dated instalments repeating every contract year;
  upload a contract PDF and the terms are proposed; deploy script confirms the timezone.
- Dashboard totals converted to the base currency; statement upload from the property
  page creates the month's payment itself (#37).
- **Dubai deeds and manager statements** (#36): DIFC/DLD title deeds; upload a
  short-let manager's statement and the net payout replaces the payment amount;
  Currencies & FX shows when a second currency is in use.
- **Major upgrades** (#35): Laravel 13, PHPUnit 13, Spatie Permission 8, Google2FA 3,
  Vite 8, PHP 8.5, Node 22; password dictionary lazy-loaded (main bundle 900 kB → 85 kB).
- **Slimdown** (#34): Docker-only scripts (bare-metal install removed), dead views and
  flows removed, one property validation rule set, cached settings, fewer queries.
- Backup dumps omit GTID markers (#33).
- **Property page rebuilt** with tabs; weekly document-expiry digest; richer P&L report
  with monthly breakdown and collection rate (#32).
- **Ops hardening** (#31): MySQL no longer published on the host; `backup.sh` dumps the
  database and archives uploads with retention and an optional off-server copy, with a
  cron installer; legacy agreement year/month columns dropped; public registration
  removed; one-off payment form hidden; dashboard monthly rent bug fixed.

## 2026-09-07

- Mobile toolbars and stat tiles (#30).
- **Portal re-skin** (#29): custom Bootstrap 5 shell modelled on the techfin portal,
  dark/light tokens, Inter, off-canvas sidebar on phones, attention bell, new dashboard.
- Deed schema fixes for the Anthropic structured-output validator (#27, #28).
- **Property form redesign**: one sectioned form for create and edit (#26).
- **Simple mode**: multi-user and bookkeeping modules hidden unless Advanced is on (#25).
- **Title deed import**: upload a scanned Cyprus Land Registry sheet, Claude extracts it,
  review, create the property with the scan attached (#24).
- Sidebar rebuilt on AdminLTE 4 classes with grouped sections (#23).
- **Rent confirmation loop** (#22): monthly payments generated from agreements, reminder
  emails with signed Yes/No links, "awaiting confirmation" page, scheduler in the
  container, SMTP settings applied to all outgoing mail.
- Deploy preflight: creates `.env`, persists `APP_KEY`, resolves port clashes (#19–#21).

## 2026-06-26

- Tenants, rental payments with arrears, expenses, P&L reports with FX, document
  lifecycle with expiry reminders, health endpoint, backups, admin 2FA enforcement,
  observability tests, schema cleanup, documentation, end-to-end smoke scripts (#6–#18).

## 2026-01-20

- Initial portal: assets, rental income, users and permissions, 2FA, SMTP, audit log,
  AdminLTE UI (#1–#5).
