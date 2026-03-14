# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.1.0] - 2026-03-14

Wip...

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

[1.0.0]: https://github.com/andreapollastri/newsletter/releases/tag/v1.0.0
