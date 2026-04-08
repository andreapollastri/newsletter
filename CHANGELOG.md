# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.0.3] - 2026-04-08

### Changed

- **Messages list** — sent messages whose audience consists entirely of testing tags are now hidden from the table. Non-sent testing messages (draft, ready, sending) remain visible for management.
- **Audience column** — recipient groups now render as native Filament badges instead of custom HTML spans; "All subscribers" shown as a warning badge, tag names as info badges.

---

## [2.0.2] - 2026-04-08

### Added

- **User roles** — `UserRole` enum (`Editor`, `Manager`, `Administrator`) stored on `users.role`; **Editor** focuses on drafting/sending campaigns; **Manager** and **Administrator** get dashboard, subscribers, templates, tags, and full campaign management.
- **Filament Users** — CRUD resource for administrators (`UserPolicy`); role and locale on create/edit; user menu entry and navigation gated by `canManageUsers()`.
- **Migrations** — `add_role_to_users_table`, `set_all_existing_users_to_legacy_administrator_role` so existing installs keep full access.
- **Role-aware policies** — `CampaignPolicy`, `MessagePolicy`, `SubscriberPolicy`, `TagPolicy`, `TemplatePolicy`, and `UserPolicy` replace a blanket “allow all authenticated” gate: e.g. editors cannot view sent/sending messages, cannot update/delete except drafts; managers/admins retain broader access per resource.

### Changed

- **Panel home URL** — Editors land on campaigns; managers and administrators land on the dashboard (`NewsletterPanelProvider`).
- **`ManageApiTokens`** — visible only to administrators (`canManageApiTokens()`).
- **`GET /api/reports/newsletter`** — returns **403** for users without management features (e.g. **Editor**), consistent with Filament reporting access.
- **Translations** — new strings for roles, user resource, and profile labels across supported locales.

### Fixed

- **Filament Messages list** (`/messages`) and **campaign → Messages** relation table — no longer apply `Message::forStatistics()` to the query. Messages whose audience is **only testing tags** were hidden even as **drafts**; they now appear like any other message. **Dashboard statistics and charts** still use `forStatistics()` so testing-only audiences stay out of aggregate metrics; **send history** for testing sends is still cleared after a completed testing send (existing job behaviour).

---

## [2.0.1] - 2026-04-08

### Added

- **OpenAPI coverage** extended for all authenticated REST routes: `GET /api/user`, `GET /api/reports/newsletter`, and full CRUD for tags, subscribers, and templates (in addition to campaigns and messages). `App\OpenApi\ApiUserEndpoint` documents the `/api/user` route implemented as a closure in `routes/api.php`.
- **`L5SwaggerDocumentationTest`** — asserts `/docs` serves JSON and the Swagger UI page does not embed a bogus `?api-docs.json` query string on the spec URL.
- Composer **`post-install-cmd`** runs `php artisan l5-swagger:generate` so `storage/api-docs/api-docs.json` is regenerated after `composer install` (helps production deployments).

### Removed

- **Per-model Laravel policies** (Campaign, Message, Subscriber, Tag, Template). Authorization no longer enforces ownership per resource.

### Changed

- **Authorization model** — `Gate::before` in `AppServiceProvider` allows any `authorize()` check for **authenticated** users (shared admin / API access; no per-user isolation via policies).
- **Filament Messages** — `MessageResource` lists **all** messages; the table query is not scoped to campaigns owned by the current user.
- **`GET /api/campaigns`** — returns **all** campaigns (no `user_id` filter on the index). Creating a campaign still sets `user_id` to the authenticated user for attribution.
- **Filament Messages list** (`/messages`): table rows are **clickable** — **view** for sent messages, **edit** for other statuses (`recordUrl`). New **Audience** column shows selected tags as blue badges, or an amber **“All subscribers”** badge when the message has no tags. New columns **Emails sent** (count of completed sends) and **Opens (total)** (sum of opens on sent rows). `MessageResource::getEloquentQuery()` eager-loads tags/campaign and adds the aggregates.
- **Filament Message → Sends** relation manager: optional filter **“Hide sends to test-only subscribers”** (enabled by default) hides rows where the recipient has tags but only testing tags; `subscriber.tags` is eager-loaded. Labels added for the messages table and filter in all six locales.
- **`App\Http\Controllers\L5SwaggerController`** extends the package Swagger controller and fixes documentation file URL generation so Laravel does not append a spurious `?api-docs.json` query string to `/docs` (Swagger UI fetch error in production). Registered via container binding in `AppServiceProvider`.

### Fixed

- **Swagger / OpenAPI UI** could return **404** or fail to load the spec when the generated JSON was missing on the server, or when the UI requested `/docs?api-docs.json`; URL generation and deploy-time generation (see `post-install-cmd` above) address this.
- **Duplicate message** action could fail after loading the list query with `withCount` / `withSum` — **`Message::replicate()`** now strips aggregate attributes (`emails_sent_count`, `opens_sum`) that are not real columns.

---

## [2.0.0] - 2026-04-08

### Added

- **REST API** powered by **Laravel Sanctum**: full CRUD endpoints for subscribers, tags, templates, campaigns, and campaign messages (`/api/*`), protected by personal access tokens with fine-grained abilities.
- **API token management** page in the Filament admin panel — create, list, and revoke Sanctum tokens with selectable abilities (`api`, `mcp`).
- **Newsletter report API** endpoint (`GET /api/reports/newsletter`) with per-campaign filtering, date range, per-message breakdown, and daily timeseries.
- **OpenAPI / Swagger documentation** via `l5-swagger` — auto-generated spec available at `/api/documentation`.
- **MCP server** (`/mcp/newsletter`) for AI integrations (Cursor, Claude Code, etc.) using `laravel/mcp`, authenticated with Sanctum Bearer tokens (`mcp` ability). Includes six tools: list campaigns, newsletter report, send history analysis, subscriber insights, generate email template HTML, and create newsletter message. Ships with a reusable `newsletter-assistant` prompt.
- **Policies** for all API-exposed models (Campaign, Message, Subscriber, Tag, Template) enforcing ownership-based authorization (removed in **2.0.1** in favor of shared access; see that release).
- **Eloquent API Resources** for consistent JSON serialization (CampaignResource, MessageResource, SubscriberResource, TagResource, TemplateResource).
- **Form request classes** for API validation (store/update for each resource, plus the newsletter report request).
- **NewsletterReportingService** — dedicated service class for building report payloads (summary, per-message stats, timeseries), shared between the API and MCP tools.
- **Testing tags** (`is_testing` on tags): optional flag in Filament when creating or editing a tag; indicator column in the tags table.
- When a message targets recipients **only via testing tags** (all tags marked as testing), its sends are **excluded from dashboard statistics** (emails sent, opens, clicks, bounces, send chart).
- After a testing-audience send **completes**, per-recipient `MessageSend` rows (and related bounces) are **removed**, so they no longer appear in send history.
- **Validation** on messages: testing tags and normal tags cannot be mixed on the same message.
- **User language** (`locale` on users): Italian, English, German, French, Spanish, or Portuguese selectable in the Filament **profile**; panel UI and notifications use the chosen locale.
- **Locale before login**: the Filament login page uses the browser `Accept-Language` header when it matches a supported code; otherwise defaults to English. New users inherit locale from the browser on creation.
- Full **translations** for all six supported languages (`de`, `en`, `es`, `fr`, `it`, `pt`) — including all new API token and MCP labels.

### Changed

- **Newsletter rate limits:** `NEWSLETTER_RATE_LIMIT_PER_MINUTE` is read from the environment again (alongside `NEWSLETTER_RATE_LIMIT_PER_HOUR` / `_PER_DAY`). Config and README describe rolling windows, queue `release()` behaviour, estimated send time, and worker/`retry_after` tuning.
- `Controller` base class now uses `AuthorizesRequests` trait for policy-based authorization in API controllers.
- `User` model updated with `HasApiTokens` trait (Sanctum) and locale support.
- Application routing now includes `api.php` and `ai.php` route files; Sanctum `abilities` middleware registered.

### New Dependencies

- `laravel/sanctum` ^4.0
- `laravel/mcp` ^0.6.5
- `darkaonline/l5-swagger` ^11.0

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
