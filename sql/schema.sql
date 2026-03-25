CREATE TABLE IF NOT EXISTS recipients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    full_name VARCHAR(190) DEFAULT '',
    tracking_token VARCHAR(64) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'sent',
    open_count INT UNSIGNED NOT NULL DEFAULT 0,
    click_count INT UNSIGNED NOT NULL DEFAULT 0,
    first_opened_at DATETIME DEFAULT NULL,
    last_opened_at DATETIME DEFAULT NULL,
    first_clicked_at DATETIME DEFAULT NULL,
    last_clicked_at DATETIME DEFAULT NULL,
    bounced_at DATETIME DEFAULT NULL,
    bounce_reason VARCHAR(500) DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_email (email),
    UNIQUE KEY uniq_tracking_token (tracking_token),
    KEY idx_status (status),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient_id BIGINT UNSIGNED NOT NULL,
    sent_message_id BIGINT UNSIGNED DEFAULT NULL,
    event_type VARCHAR(40) NOT NULL,
    event_data LONGTEXT,
    ip_address VARCHAR(64) DEFAULT '',
    user_agent VARCHAR(500) DEFAULT '',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_recipient (recipient_id),
    KEY idx_sent_message_id (sent_message_id),
    KEY idx_event_type (event_type),
    KEY idx_created_at (created_at),
    CONSTRAINT fk_events_recipient FOREIGN KEY (recipient_id) REFERENCES recipients(id) ON DELETE CASCADE,
    CONSTRAINT fk_events_sent_message FOREIGN KEY (sent_message_id) REFERENCES sent_messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
