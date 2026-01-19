# i-portal.me

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

- PHP **8.3+**
- Composer
- Node.js **18+**
- npm
- MySQL **8.0+**
- Git

---

## Installation

### 1. Clone the repository
```bash
git clone git@github.com:chrysanthosk/i-portal.me.git
cd i-portal.me
```

### 2. Install PHP dependencies
```bash
composer install
```

### 3. Install Node dependencies
```bash
npm install
```

### 4. Environment configuration
```bash
cp .env.example .env
php artisan key:generate
```

### 5. MySQL configuration

Create a MySQL database:

```sql
CREATE DATABASE i_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Update your `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=i_portal
DB_USERNAME=your_mysql_user
DB_PASSWORD=your_mysql_password
```

---

### 6. Run migrations & seed initial data
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

---

### 7. Build frontend assets
```bash
npm run build
```

For development:
```bash
npm run dev
```

---

### 8. Start the application
```bash
php artisan serve
```

Open:
```
http://127.0.0.1:8000
```

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
