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
