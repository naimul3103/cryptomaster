# 🪙 CryptoMaster

A paper-trading cryptocurrency simulator built with vanilla HTML/CSS/JS on the frontend and PHP + MySQL on the backend. Users get a virtual balance to buy and sell real-time crypto prices pulled from the Binance public API — no real money involved.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue?logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-orange?logo=mysql)
![License](https://img.shields.io/badge/license-MIT-green)

---

## ✨ Features

- 📈 Live market data (120 top USDT pairs) via Binance public API
- 🕯️ Interactive candlestick charts with multiple intervals
- 💼 Per-user virtual wallet — buy, sell, track P&L
- 🔐 Session-based authentication (register / login / logout)
- 👑 Admin dashboard — manage users, adjust balances, promote roles
- 🛡️ Production-hardened backend (no credentials in source, secure session, safe error handling)

---

## 📁 Project Structure

```
cryptomaster/
├── assets/               # Static assets (images, icons, etc.)
├── database/
│   └── setup.sql         # One-time DB creation script
├── index.html            # Single-page frontend application
├── api.php               # REST-style PHP backend
├── db.php                # PDO database layer + schema init
├── .env.example          # Environment variable template ← copy to .env
├── .env                  # Your local secrets  ← NEVER commit this
├── .htaccess             # Apache security rules
├── .gitignore
└── README.md
```

---

## 🚀 Quick Start (Local — XAMPP / Laragon)

### Prerequisites

| Requirement | Version |
|-------------|---------|
| PHP         | 8.1+    |
| MySQL       | 8.0+    |
| Apache      | 2.4+    |
| cURL PHP extension | enabled |

### 1. Clone the repository

```bash
git clone https://github.com/your-username/cryptomaster.git
cd cryptomaster
```

### 2. Create the database

```bash
mysql -u root -p < database/setup.sql
```

Or open **phpMyAdmin** and create a database named `cryptomaster`.

### 3. Configure environment variables

```bash
cp .env.example .env
```

Edit `.env` with your values:

```env
DB_HOST=127.0.0.1
DB_NAME=cryptomaster
DB_USER=root
DB_PASS=your_mysql_password

ADMIN_EMAIL=admin@yourdomain.com
ADMIN_PASS=a_very_strong_password

APP_ENV=development
APP_URL=http://localhost
```

> ⚠️ **Never commit `.env` to version control.** It is listed in `.gitignore`.

### 4. Place files in your web root

- **XAMPP**: copy the `cryptomaster/` folder to `C:\xampp\htdocs\`
- **Laragon**: copy to `C:\laragon\www\`

### 5. Open in browser

```
http://localhost/cryptomaster/
```

The database tables are created automatically on the first request. The admin account from `.env` is seeded at the same time.

---

## 🌐 Production Deployment

### Shared Hosting (cPanel)

1. Upload all files to `public_html/` (or a subdirectory).
2. Create a MySQL database and user via cPanel → MySQL Databases.
3. Upload `.env` with production credentials (**do not** upload `.env.example` if it contains hints).
4. Set `APP_ENV=production` in `.env` — this hides raw DB errors and enforces secure cookies.
5. Ensure `mod_rewrite` and `mod_headers` are enabled for `.htaccess` to work.

### Recommended: Dedicated DB User

Instead of using `root`, create a least-privilege MySQL user:

```sql
CREATE USER 'cryptomaster_app'@'localhost' IDENTIFIED BY 'strong_random_password';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE ON `cryptomaster`.* TO 'cryptomaster_app'@'localhost';
FLUSH PRIVILEGES;
```

Then update `.env`:
```env
DB_USER=cryptomaster_app
DB_PASS=strong_random_password
```

---

## 🔐 Security Notes

| Area | What was done |
|------|--------------|
| Credentials | Moved from source code to `.env` (loaded at runtime) |
| `.env` protection | Blocked via `.htaccess` — returns 403 if accessed via browser |
| Session fixation | `session_regenerate_id(true)` called on login |
| Secure cookies | `HttpOnly`, `SameSite=Lax`; `Secure` flag auto-enabled in production |
| SQL injection | All queries use PDO prepared statements with bound parameters |
| User enumeration | Constant-time dummy hash check when username not found |
| DB errors in prod | Raw exceptions are suppressed; safe message shown instead |
| Wallet symbol input | Sanitised server-side before insert |
| Admin-only routes | `requireAdmin()` checked on every admin action |
| Directory listing | Disabled via `Options -Indexes` in `.htaccess` |

---

## 🛠️ API Reference

All endpoints are at `api.php?action=<action>` (GET for read, POST body for writes).

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `register` | POST | — | Create account |
| `login` | POST | — | Start session |
| `logout` | GET | User | End session |
| `me` | GET | User | Current user + wallet |
| `market` | GET | — | Top 120 USDT pairs |
| `candles` | GET | — | OHLC data for a symbol |
| `wallet_get` | GET | User | Balance + holdings |
| `wallet_save` | POST | User | Persist trade |
| `wallet_topup` | POST | User | Add $20 000 test tokens |
| `admin_users` | GET | Admin | List all users |
| `admin_delete_user` | POST | Admin | Delete a user |
| `admin_update_balance` | POST | Admin | Set user balance |
| `admin_make_admin` | POST | Admin | Promote to admin |

---

## 🤝 Contributing

1. Fork the repo
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Commit your changes: `git commit -m "feat: add my feature"`
4. Push and open a Pull Request

Please do **not** commit `.env` or any file containing real credentials.

---

## 📄 License

MIT © 2025 — see [LICENSE](LICENSE) for details.
# cryptomaster
