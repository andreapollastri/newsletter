# Newsletter System

A complete newsletter management system for Laravel, built with Filament. Manage subscribers, campaigns, HTML templates, scheduled sending, and full tracking—all from a modern admin panel.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
- [Sending Newsletters](#sending-newsletters)
- [Monitoring & Analytics](#monitoring--analytics)
- [Rate Limiting](#rate-limiting)
- [Public Routes](#public-routes)
- [Scheduled Tasks](#scheduled-tasks)
- [Artisan Commands](#artisan-commands)
- [Troubleshooting](#troubleshooting)

---

## Features

| Feature                   | Description                                           |
| ------------------------- | ----------------------------------------------------- |
| **Subscriber Management** | Import/export CSV, tagging, status management         |
| **Campaigns & Messages**  | Hierarchical organization of campaigns and messages   |
| **HTML Templates**        | Customizable templates with placeholder support       |
| **Scheduled Sending**     | Automatic delivery via cron                           |
| **Full Tracking**         | Opens, clicks, and unsubscribe tracking               |
| **Targeting**             | Filter recipients by tags and status                  |
| **Dashboard**             | Statistics and monitoring widgets                     |
| **Rate Limiting**         | Configurable per-minute, per-hour, and per-day limits |
| **Bounce Detection**      | IMAP integration for bounce processing                |

---

## Requirements

- PHP 8.2+
- Laravel 12
- Filament 5
- Database (SQLite, MySQL, or PostgreSQL)
- Queue driver (database, Redis, etc.)

---

## Installation

1. **Clone the repository and install dependencies:**

```bash
git clone <repository-url> newsletter
cd newsletter
composer install
```

1. **Configure the environment:**

```bash
cp .env.example .env
php artisan key:generate
```

1. **Run migrations:**

```bash
php artisan migrate
```

1. **Build frontend assets:**

```bash
npm install
npm run build
```

1. **Create an admin user** (if not using seed data):

```bash
php artisan make:filament-user
```

---

## Configuration

### Mail

Configure your SMTP settings in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS="newsletter@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### Newsletter

| Variable                           | Description                           | Default |
| ---------------------------------- | ------------------------------------- | ------- |
| `NEWSLETTER_TRACKING_ENABLED`      | Enable open/click tracking            | `true`  |
| `NEWSLETTER_RATE_LIMIT_PER_MINUTE` | Max emails per minute (0 = unlimited) | `0`     |
| `NEWSLETTER_RATE_LIMIT_PER_HOUR`   | Max emails per hour (0 = unlimited)   | `0`     |
| `NEWSLETTER_RATE_LIMIT_PER_DAY`    | Max emails per day (0 = unlimited)    | `0`     |

### IMAP (Bounce Detection)

Optional configuration for processing bounced emails:

```env
NEWSLETTER_IMAP_HOST=imap.yourdomain.com
NEWSLETTER_IMAP_PORT=993
NEWSLETTER_IMAP_USERNAME=your-username
NEWSLETTER_IMAP_PASSWORD=your-password
NEWSLETTER_IMAP_ENCRYPTION=ssl
NEWSLETTER_IMAP_FOLDER=INBOX
```

---

## Quick Start

1. **Seed sample data** (optional, recommended for testing):

```bash
php artisan newsletter:seed-data
```

1. **Start the queue worker:**

```bash
./start-worker.sh
```

Or manually:

```bash
php artisan queue:work --tries=3 --timeout=90
```

1. **Access the admin panel:**

- **URL:** `https://newsletter.test` (Laravel Herd) or `http://localhost:8000`
- **Default credentials** (after seeding): `admin@newsletter.test` / `password`

---

## Sending Newsletters

### Immediate Sending

1. Go to **Newsletter > Messages**
2. Create a new message or select an existing one
3. Set status to **Ready**
4. Click **Send Now**
5. Emails are queued and sent automatically via the queue worker

### Scheduled Sending

1. Create or edit a message
2. Set the **Scheduled Date** field
3. The system sends automatically at the scheduled time (requires cron—see [Scheduled Tasks](#scheduled-tasks))

---

## Monitoring & Analytics

- **Dashboard:** Main KPIs and send statistics
- **Messages:** Status and send counts per message
- **Message Details:** Individual tracking (opens, clicks) per recipient

---

## Rate Limiting

Configure sending limits to comply with your SMTP provider's constraints:

```env
NEWSLETTER_RATE_LIMIT_PER_MINUTE=60
NEWSLETTER_RATE_LIMIT_PER_HOUR=1000
NEWSLETTER_RATE_LIMIT_PER_DAY=10000
```

Limits are progressive: daily overrides hourly, hourly overrides per-minute.

**Check current rate limits:**

```bash
php artisan newsletter:rate-limits
```

---

## Public Routes

The following routes are available for public use:

| Route                               | Method | Description                          |
| ----------------------------------- | ------ | ------------------------------------ |
| `/subscribe`                        | GET    | Subscription form                    |
| `/subscribe`                        | POST   | Process subscription                 |
| `/subscribe/confirm/{token}`        | GET    | Confirm subscription (double opt-in) |
| `/unsubscribe/{subscriber}`         | GET    | Unsubscribe form                     |
| `/unsubscribe/{subscriber}/confirm` | POST   | Confirm unsubscribe                  |

---

## Scheduled Tasks

Add the Laravel scheduler to your crontab:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

| Task                         | Schedule         | Description                                    |
| ---------------------------- | ---------------- | ---------------------------------------------- |
| `newsletter:send-scheduled`  | Every minute     | Sends messages with scheduled date in the past |
| `newsletter:process-bounces` | Every 15 minutes | Processes bounced emails via IMAP              |
| `backup:run`                 | Daily at 03:00   | Runs application backup                        |
| `backup:clean`               | Daily at 04:00   | Cleans old backups                             |

---

## Artisan Commands

| Command                      | Description                                                        |
| ---------------------------- | ------------------------------------------------------------------ |
| `newsletter:seed-data`       | Populate database with sample subscribers, campaigns, and messages |
| `newsletter:send-scheduled`  | Manually trigger scheduled message sending                         |
| `newsletter:process-pending` | Process pending emails in the queue                                |
| `newsletter:process-bounces` | Process bounced emails from IMAP                                   |
| `newsletter:rate-limits`     | Display current rate limit status                                  |

---

## Take it to production — cipi.sh

Once your app is ready, deploy it to any Ubuntu VPS with [cipi.sh](https://cipi.sh) — an open-source CLI built exclusively for Laravel.

- Full app isolation — own Linux user, PHP-FPM pool & database per app
- Zero-downtime deploys with instant rollback via Deployer
- Let's Encrypt SSL, Fail2ban, UFW firewall — all automated
- Multi-PHP (7.4 → 8.5), queue workers, S3 backups, auto-deploy webhooks
- AI Agent ready — any AI with SSH access can deploy & manage your server

```bash
wget -O - https://raw.githubusercontent.com/andreapollastri/cipi/refs/heads/latest/install.sh | bash
```

→ Learn more at [cipi.sh](https://cipi.sh)

---

## Troubleshooting

### Emails not sending

1. Ensure the queue worker is running: `./start-worker.sh` or `php artisan queue:work`
2. Manually process pending: `php artisan newsletter:process-pending`
3. Check the jobs table: `php artisan tinker --execute="DB::table('jobs')->count()"`

### Test email delivery

```bash
php artisan tinker --execute="Mail::raw('Test', fn(\$m) => \$m->to('test@example.com'))"
```

### Verify rate limits

```bash
php artisan newsletter:rate-limits
```

### Frontend changes not visible

Run `npm run build` or `npm run dev` to compile assets.

---

## License

MIT

---

## Credits

Created by [Andrea Pollastri](https://web.ap.it)
