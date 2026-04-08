# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.4] - 2026-04-08

### Changed

- **Newsletter rate limits:** `NEWSLETTER_RATE_LIMIT_PER_MINUTE` is read from the environment again (with `NEWSLETTER_RATE_LIMIT_PER_HOUR` / `_PER_DAY`). Config and README describe rolling windows, queue `release()` behaviour, estimated send time, and worker/`retry_after` tuning.

### Added

- **Testing tags** (`is_testing` on tags): optional flag in Filament when creating or editing a tag; indicator column in the tags table.
- When a message targets recipients **only via testing tags** (at least one tag, all marked as testing), its sends are **excluded from dashboard statistics** (emails sent, opens, clicks, bounces, send chart).
- After a testing-audience send **completes**, per-recipient `MessageSend` rows (and related bounces) are **removed**, so they no longer appear in message send history or in a subscriber’s “messages received” list.
- **Validation** on messages: testing tags and normal tags cannot be mixed on the same message.
- **User language** (`locale` on users): Italian, English, German, French, Spanish, or Portuguese selectable in the Filament **profile**; panel UI and notifications use the chosen locale (`HasLocalePreference` on `User`). JSON translations in `lang/de.json`, `lang/fr.json`, `lang/es.json`, and `lang/pt.json`.
- **Locale before login**: the Filament login (and guest panel) uses `Accept-Language` when it matches a supported code (primary subtag, e.g. `pt-BR` → `pt`); otherwise **English**. New users get `locale` from the browser on create when not set, with **English** as fallback (`UserLocale::negotiateFromRequest`).

---

## [1.0.3] - 2026-04-08

### Added

- Campaign **slug** field (unique), pre-filled from the campaign name in the Filament form; automatic numeric suffix when the slug collides with an existing one.
- **UTM parameters** on outbound newsletter links (`utm_source=nl`, `utm_medium=newsletter`, `utm_campaign=<campaign slug>`, `utm_content=<message id>`), merged onto HTTP(S) URLs before click tracking.

---

## [1.0.2] - 2026-04-08

### Fixed

- Env issue while Composer installing

## Deleted

- MCP configurations

---

## [1.0.1] - 2026-04-08

### Fixed

- Issue related to Composer dependecies

---

## [1.0.0] - 2026-03-04

### Added

- **Subscriber Management**
    - CRUD operations for subscribers
    - CSV import and export
    - Tagging system
    - Status management (pending, confirmed, unsubscribed)
    - Double opt-in subscription flow

- **Campaigns & Messages**
    - Hierarchical campaign organization
    - Message creation with subject, content, and template selection
    - Message status workflow (draft, ready, sending, sent)
    - Immediate and scheduled sending

- **HTML Templates**
    - Customizable HTML templates
    - Placeholder support for personalization

- **Sending**
    - Queue-based email delivery
    - Scheduled sending via cron (`newsletter:send-scheduled`)
    - Rate limiting (per-minute, per-hour, per-day)

- **Tracking**
    - Open tracking
    - Click tracking
    - Unsubscribe tracking
    - Per-recipient send logs

- **Targeting**
    - Filter recipients by tags
    - Filter by subscriber status

- **Admin Panel (Filament 5)**
    - Dashboard with statistics and widgets
    - Subscribers, Campaigns, Messages, Templates, Tags resources
    - Multi-factor authentication (App, Email)
    - Top navigation layout

- **Public Routes**
    - `/subscribe` — subscription form and confirmation
    - `/unsubscribe/{subscriber}` — unsubscribe flow

- **Artisan Commands**
    - `newsletter:seed-data` — seed sample data
    - `newsletter:send-scheduled` — send scheduled messages
    - `newsletter:process-pending` — process pending queue
    - `newsletter:process-bounces` — process IMAP bounces
    - `newsletter:rate-limits` — display rate limit status

- **Bounce Detection**
    - IMAP integration for bounce processing
    - Scheduled `newsletter:process-bounces` every 15 minutes

- **Backup**
    - Spatie Laravel Backup integration
    - SFTP backup destination support
    - Daily backup schedule
