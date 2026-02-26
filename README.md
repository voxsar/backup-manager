# Backup Manager

A Laravel 11 application with a **FilamentPHP v3** admin panel that lets you manage database backups, configure schedules, and deliver notifications to **WhatsApp** and **Mattermost**.

---

## Features

| Feature | Details |
|---------|---------|
| **Database Credentials** | Store encrypted MySQL / PostgreSQL connection details |
| **Backup Configurations** | Map a credential to a cron schedule + retention policy |
| **Notification Channels** | WhatsApp (via CallMeBot API) and Mattermost (incoming webhook) |
| **Run Now** | Trigger any backup immediately from the admin UI |
| **Automatic Scheduling** | Built-in Laravel scheduler fires `backup:run-scheduled` every minute and runs any due jobs |
| **Pruning** | Old dump files are removed automatically based on the configured retention window |
| **Docker** | Single-container setup (PHP-FPM + Nginx + Scheduler + Queue via Supervisor) |

---

## Quick Start with Docker

```bash
# 1. Clone the repo
git clone https://github.com/voxsar/backup-manager.git
cd backup-manager

# 2. Start the container (binds to host port 5828)
docker compose up -d --build

# 3. Open the admin panel
open http://localhost:5828/admin

# Default credentials (seeded on first boot):
#   Email:    admin@example.com
#   Password: password
```

> The container runs behind port **5828** so it can be placed behind a reverse-proxy (Nginx + Certbot) without any changes.

---

## Development Setup (without Docker)

```bash
# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Copy environment file and generate key
cp .env.example .env
php artisan key:generate

# Run migrations + seed
php artisan migrate --seed

# Start development server
php artisan serve --port=5828
```

---

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_KEY` | *(generated)* | Laravel encryption key — **must be set** |
| `APP_URL` | `http://localhost:5828` | Public URL (used in links) |
| `DB_CONNECTION` | `sqlite` | App's own database driver |
| `DB_DATABASE` | `database/database.sqlite` | Path to SQLite file (or MySQL/PG DSN) |
| `WHATSAPP_API_URL` | `https://api.callmebot.com/whatsapp.php` | CallMeBot endpoint |
| `BACKUP_DISK` | `local` | Laravel filesystem disk for storing dumps |

---

## Notification Channels

### WhatsApp (CallMeBot)

1. Send the message **"I allow callmebot to send me messages"** to `+34 644 68 79 97` on WhatsApp.
2. You will receive an API key by reply.
3. In the admin panel → **Notification Channels** → create a WhatsApp channel with your phone number and API key.

### Mattermost

1. In Mattermost: **Integrations → Incoming Webhooks → Add Incoming Webhook**.
2. Copy the webhook URL.
3. In the admin panel → **Notification Channels** → create a Mattermost channel with the webhook URL.

---

## Admin Panel Structure

```
/admin
├── Dashboard
├── Database Credentials   ← add / edit DB connections
├── Backup Configurations  ← schedule, retention, assign notifications
└── Notification Channels  ← WhatsApp & Mattermost
```

---

## Architecture

```
Docker container (port 5828)
└── supervisord
    ├── nginx          (listens on 8080 internally)
    ├── php-fpm
    ├── laravel-scheduler  (runs artisan schedule:run every 60 s)
    └── laravel-queue      (processes queued jobs)
```

Backup dumps are stored under `storage/app/backups/` (mapped to a named Docker volume so they survive container restarts).

---

## Security Notes

- Database passwords and notification channel configs are **encrypted at rest** using Laravel's `Crypt` facade (AES-256-CBC).
- `APP_KEY` must be kept secret — it is the master encryption key.
- The admin panel requires authentication; the default seeded user should be changed in production.