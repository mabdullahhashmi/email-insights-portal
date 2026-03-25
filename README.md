# PHP Email Insights Portal

Standalone recipient-level tracking portal for emails sent manually from webmail.

## Features

- Recipient-first tracking (no campaign dependency)
- Multiple email lists (welcome, followup, etc.)
- Open tracking via pixel endpoint
- Click tracking via redirect endpoint
- Bounce webhook for delivered estimate
- HTML tracker generator for any pasted email template
- Automatic click tracking rewrite for all `<a href="...">` links
- Sent email history storage (original + tracked HTML)
- Recipient detail page with past emails and event timeline
- Built-in HTML preview before sending
- Login-protected dashboard
- List-scoped analytics pages
- Send now and schedule email from portal
- Manual and cron queue runner

## Folder structure

- `config/config.example.php` - project configuration template
- `sql/schema.sql` - MySQL schema
- `src/` - core services
- `public/` - portal pages and tracking endpoints

## Setup

1. Create a MySQL database.
2. Import `sql/schema.sql`.
3. Copy `config/config.example.php` to `config/config.php`.
4. Update values in `config/config.php`:
   - `base_url` should be your public portal URL (example: `https://abdullahhashmi.com/email-insights`)
   - DB credentials
   - `portal.username`
   - `portal.password_hash`
   - `portal.webhook_secret`
  - `mailer.from_email`
  - `mailer.from_name`
  - `mailer.reply_to`
  - `mailer.default_timezone`
5. Open `login.php` in browser and sign in.

## Migration for existing installs

If you already imported the old schema, run these SQL statements once:

```sql
CREATE TABLE IF NOT EXISTS email_lists (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_list_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sent_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipient_id BIGINT UNSIGNED NOT NULL,
  list_id BIGINT UNSIGNED DEFAULT NULL,
  subject VARCHAR(255) DEFAULT '',
  original_html LONGTEXT,
  tracked_html LONGTEXT,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_recipient_id (recipient_id),
  KEY idx_list_id (list_id),
  KEY idx_created_at (created_at),
  CONSTRAINT fk_sent_messages_recipient FOREIGN KEY (recipient_id) REFERENCES recipients(id) ON DELETE CASCADE,
  CONSTRAINT fk_sent_messages_list FOREIGN KEY (list_id) REFERENCES email_lists(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE events ADD COLUMN sent_message_id BIGINT UNSIGNED DEFAULT NULL;
ALTER TABLE events ADD KEY idx_sent_message_id (sent_message_id);
ALTER TABLE events ADD CONSTRAINT fk_events_sent_message FOREIGN KEY (sent_message_id) REFERENCES sent_messages(id) ON DELETE SET NULL;

ALTER TABLE sent_messages ADD COLUMN recipient_email_snapshot VARCHAR(190) NOT NULL DEFAULT '' AFTER list_id;
ALTER TABLE sent_messages ADD COLUMN recipient_name_snapshot VARCHAR(190) DEFAULT '' AFTER recipient_email_snapshot;
ALTER TABLE sent_messages ADD COLUMN send_status VARCHAR(30) NOT NULL DEFAULT 'generated' AFTER tracked_html;
ALTER TABLE sent_messages ADD COLUMN scheduled_at_utc DATETIME DEFAULT NULL AFTER send_status;
ALTER TABLE sent_messages ADD COLUMN scheduled_timezone VARCHAR(64) NOT NULL DEFAULT 'UTC' AFTER scheduled_at_utc;
ALTER TABLE sent_messages ADD COLUMN sent_at DATETIME DEFAULT NULL AFTER scheduled_timezone;
ALTER TABLE sent_messages ADD COLUMN send_attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER sent_at;
ALTER TABLE sent_messages ADD COLUMN last_error VARCHAR(500) DEFAULT '' AFTER send_attempts;
ALTER TABLE sent_messages ADD COLUMN updated_at DATETIME DEFAULT NULL AFTER created_at;
ALTER TABLE sent_messages ADD KEY idx_send_status_scheduled (send_status, scheduled_at_utc);

UPDATE sent_messages sm
INNER JOIN recipients r ON r.id = sm.recipient_id
SET sm.recipient_email_snapshot = r.email,
    sm.recipient_name_snapshot = r.full_name
WHERE sm.recipient_email_snapshot = '';
```

## Password hash command

Run this once on any PHP shell:

```bash
php -r "echo password_hash('YourStrongPassword', PASSWORD_DEFAULT), PHP_EOL;"
```

Paste output in `portal.password_hash`.

## Daily workflow (webmail sending)

1. Open `generate.php`.
2. Select existing recipient or enter recipient email (auto-creates recipient).
3. Select list or create a new list name.
4. Paste raw HTML email and generate tracked output.
5. Review HTML preview and copy tracked HTML.
6. Paste into Hostinger webmail compose source and send.
7. Open `dashboard.php` for summary and click recipient email for full history/timeline.

## Sending from portal

`generate.php` now supports:

1. Generate only (no send)
2. Send now using `mail()`
3. Schedule by local datetime + timezone

Scheduled sends can be processed two ways:

1. Manual: open `send-queue.php` and click **Run Due Sends Now**
2. Cron: call `cron-send.php?s=YOUR_WEBHOOK_SECRET` every minute

Note: inbox placement (Primary tab) depends on sender reputation/content and cannot be guaranteed by code alone.

## Endpoints

- Open pixel: `/track/open.php?t={token}&mid={message_id}`
- Click tracker: `/track/click.php?t={token}&u={base64url_target}&mid={message_id}`
- Bounce webhook: `/webhook/bounce.php` (POST JSON)

Bounce webhook JSON example:

```json
{
  "secret": "your_webhook_secret",
  "token": "recipient_tracking_token",
  "reason": "Mailbox not found"
}
```

## Accuracy notes

- Opens are estimates (image blocking/proxy/privacy can affect data).
- Clicks are more reliable than opens.
- Delivered estimate is calculated as sent minus bounced.
- Portal previews no longer include open pixel to prevent false open inflation.
