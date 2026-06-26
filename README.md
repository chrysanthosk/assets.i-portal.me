# assets.i-portal.me

A modern **Laravel 12 + AdminLTE 4 (Bootstrap 5)** portal featuring authentication, roles & permissions, 2FA, SMTP configuration, and a clean, extensible settings architecture.

---

## Features

- Username + password authentication
- Role & permission management (Spatie)
- User management (Admin / User permission sets)
- Profile management (name, email with OTP confirmation)
- Password strength meter (zxcvbn)
- Two-Factor Authentication (Google Authenticator)
- SMTP configuration & test email
- Dark / Light mode toggle (persistent)
- Expandable Settings menu (AdminLTE treeview)
- Inline validation feedback
- Modal delete confirmations

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

## Security Notes

- Change the default admin password immediately
- Do not commit `.env`
- Use HTTPS in production
- Enable 2FA for admin accounts
- Use strong SMTP credentials

---

## Roadmap

- Additional modules (Finance, CRM, Reports)
- Audit logs
- API authentication
- Webhooks
- Multi-tenant support

---

## License

Private / Internal Use

---

## Author

**Chrysanthos Kattimeris**
