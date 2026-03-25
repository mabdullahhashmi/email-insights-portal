<?php

declare(strict_types=1);

final class TrackingService
{
    public static function createRecipient(PDO $pdo, string $email, string $name): ?array
    {
        $email = trim(strtolower($email));
        $name = trim($name);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $token = bin2hex(random_bytes(20));

        $stmt = $pdo->prepare(
            'INSERT INTO recipients (email, full_name, tracking_token, status, created_at) VALUES (:email, :full_name, :token, :status, NOW())'
        );

        $stmt->execute([
            ':email' => $email,
            ':full_name' => $name,
            ':token' => $token,
            ':status' => 'sent',
        ]);

        return [
            'id' => (int) $pdo->lastInsertId(),
            'email' => $email,
            'full_name' => $name,
            'token' => $token,
        ];
    }

    public static function findRecipientByEmail(PDO $pdo, string $email): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM recipients WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => trim(strtolower($email))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findRecipientById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM recipients WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getOrCreateRecipient(PDO $pdo, string $email, string $name = ''): ?array
    {
        $existing = self::findRecipientByEmail($pdo, $email);
        if ($existing) {
            if (trim($name) !== '' && trim((string) $existing['full_name']) === '') {
                $stmt = $pdo->prepare('UPDATE recipients SET full_name = :full_name, updated_at = NOW() WHERE id = :id');
                $stmt->execute([
                    ':full_name' => trim($name),
                    ':id' => (int) $existing['id'],
                ]);
                $existing['full_name'] = trim($name);
            }
            return $existing;
        }

        return self::createRecipient($pdo, $email, $name);
    }

    public static function listEmailLists(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT * FROM email_lists ORDER BY name ASC');
        return $stmt->fetchAll() ?: [];
    }

    public static function createEmailList(PDO $pdo, string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $stmt = $pdo->prepare('INSERT INTO email_lists (name, created_at) VALUES (:name, NOW())');
        $stmt->execute([':name' => $name]);

        return [
            'id' => (int) $pdo->lastInsertId(),
            'name' => $name,
        ];
    }

    public static function getOrCreateEmailList(PDO $pdo, string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM email_lists WHERE name = :name LIMIT 1');
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch();

        if ($row) {
            return $row;
        }

        return self::createEmailList($pdo, $name);
    }

    public static function createSentMessage(
        PDO $pdo,
        int $recipientId,
        ?int $listId,
        string $subject,
        string $originalHtml,
        string $trackedHtml,
        string $sendStatus = 'generated',
        ?string $scheduledAtUtc = null,
        string $scheduledTimezone = 'UTC'
    ): int {
        $stmt = $pdo->prepare(
            'INSERT INTO sent_messages (
                recipient_id,
                list_id,
                recipient_email_snapshot,
                recipient_name_snapshot,
                subject,
                original_html,
                tracked_html,
                send_status,
                scheduled_at_utc,
                scheduled_timezone,
                created_at,
                updated_at
             )
             SELECT
                r.id,
                :list_id,
                r.email,
                r.full_name,
                :subject,
                :original_html,
                :tracked_html,
                :send_status,
                :scheduled_at_utc,
                :scheduled_timezone,
                NOW(),
                NOW()
             FROM recipients r
             WHERE r.id = :recipient_id'
        );

        $stmt->bindValue(':recipient_id', $recipientId, PDO::PARAM_INT);
        if ($listId === null) {
            $stmt->bindValue(':list_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':list_id', $listId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':subject', trim($subject));
        $stmt->bindValue(':original_html', $originalHtml);
        $stmt->bindValue(':tracked_html', $trackedHtml);
        $stmt->bindValue(':send_status', $sendStatus);
        if ($scheduledAtUtc === null) {
            $stmt->bindValue(':scheduled_at_utc', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':scheduled_at_utc', $scheduledAtUtc);
        }
        $stmt->bindValue(':scheduled_timezone', trim($scheduledTimezone) !== '' ? $scheduledTimezone : 'UTC');
        $stmt->execute();

        return (int) $pdo->lastInsertId();
    }

    public static function updateSentMessageTrackedHtml(PDO $pdo, int $sentMessageId, string $trackedHtml): void
    {
        $stmt = $pdo->prepare('UPDATE sent_messages SET tracked_html = :tracked_html, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':tracked_html' => $trackedHtml,
            ':id' => $sentMessageId,
        ]);
    }

    public static function updateSentMessageSchedule(PDO $pdo, int $sentMessageId, ?string $scheduledAtUtc, string $scheduledTimezone, string $sendStatus): void
    {
        $stmt = $pdo->prepare(
            'UPDATE sent_messages
             SET scheduled_at_utc = :scheduled_at_utc,
                 scheduled_timezone = :scheduled_timezone,
                 send_status = :send_status,
                 updated_at = NOW()
             WHERE id = :id'
        );

        if ($scheduledAtUtc === null) {
            $stmt->bindValue(':scheduled_at_utc', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':scheduled_at_utc', $scheduledAtUtc);
        }

        $stmt->bindValue(':scheduled_timezone', trim($scheduledTimezone) !== '' ? $scheduledTimezone : 'UTC');
        $stmt->bindValue(':send_status', $sendStatus);
        $stmt->bindValue(':id', $sentMessageId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public static function listDueScheduledMessages(PDO $pdo, int $limit = 50): array
    {
        $stmt = $pdo->prepare(
            'SELECT sm.*, r.email AS recipient_email, r.full_name AS recipient_name, r.tracking_token
             FROM sent_messages sm
             INNER JOIN recipients r ON r.id = sm.recipient_id
             WHERE sm.send_status = "scheduled"
               AND sm.scheduled_at_utc IS NOT NULL
               AND sm.scheduled_at_utc <= UTC_TIMESTAMP()
             ORDER BY sm.scheduled_at_utc ASC, sm.id ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public static function markMessageSent(PDO $pdo, int $sentMessageId): void
    {
        $stmt = $pdo->prepare(
            'UPDATE sent_messages
             SET send_status = "sent",
                 sent_at = NOW(),
                 send_attempts = send_attempts + 1,
                 last_error = "",
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([':id' => $sentMessageId]);
    }

    public static function markMessageFailed(PDO $pdo, int $sentMessageId, string $error): void
    {
        $stmt = $pdo->prepare(
            'UPDATE sent_messages
             SET send_status = "failed",
                 send_attempts = send_attempts + 1,
                 last_error = :last_error,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $sentMessageId,
            ':last_error' => substr($error, 0, 500),
        ]);
    }

    public static function findRecipientByToken(PDO $pdo, string $token): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM recipients WHERE tracking_token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function logEvent(PDO $pdo, int $recipientId, string $eventType, array $data = [], ?int $sentMessageId = null): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO events (recipient_id, sent_message_id, event_type, event_data, ip_address, user_agent, created_at)
             VALUES (:recipient_id, :sent_message_id, :event_type, :event_data, :ip_address, :user_agent, NOW())'
        );

        if ($sentMessageId === null) {
            $stmt->bindValue(':sent_message_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':sent_message_id', $sentMessageId, PDO::PARAM_INT);
        }

        $stmt->bindValue(':recipient_id', $recipientId, PDO::PARAM_INT);
        $stmt->bindValue(':event_type', $eventType);
        $stmt->bindValue(':event_data', json_encode($data, JSON_UNESCAPED_SLASHES));
        $stmt->bindValue(':ip_address', self::clientIp());
        $stmt->bindValue(':user_agent', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500));
        $stmt->execute();
    }

    public static function trackOpen(PDO $pdo, array $recipient, ?int $sentMessageId = null): void
    {
        $recipientId = (int) $recipient['id'];
        $ip = self::clientIp();
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $humanLikely = self::isHumanLikely();
        $proxyLikely = self::isOpenProxyLikely();
        $duplicateLikely = self::hasRecentOpenFingerprint($pdo, $recipientId, $sentMessageId, $ip, $ua);

        $shouldIncrement = $humanLikely && !$proxyLikely && !$duplicateLikely && $sentMessageId !== null;

        if ($shouldIncrement) {
            $stmt = $pdo->prepare('SELECT open_count, first_opened_at FROM recipients WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $recipientId]);
            $fresh = $stmt->fetch() ?: ['open_count' => 0, 'first_opened_at' => null];

            $openCount = (int) $fresh['open_count'] + 1;
            $firstOpenedAt = $fresh['first_opened_at'] ?: date('Y-m-d H:i:s');

            $stmt = $pdo->prepare(
                'UPDATE recipients SET open_count = :open_count, first_opened_at = :first_opened_at, last_opened_at = NOW(), status = :status, updated_at = NOW() WHERE id = :id'
            );

            $stmt->execute([
                ':open_count' => $openCount,
                ':first_opened_at' => $firstOpenedAt,
                ':status' => 'opened',
                ':id' => $recipientId,
            ]);
        }

        $eventType = 'open';
        if ($sentMessageId === null) {
            $eventType = 'open_unscoped';
        } elseif (!$humanLikely) {
            $eventType = 'open_bot';
        } elseif ($proxyLikely) {
            $eventType = 'open_proxy';
        } elseif ($duplicateLikely) {
            $eventType = 'open_duplicate';
        }

        self::logEvent($pdo, $recipientId, $eventType, [
            'source' => 'pixel',
            'human_likely' => $humanLikely,
            'proxy_likely' => $proxyLikely,
            'duplicate_filtered' => $duplicateLikely,
        ], $sentMessageId);
    }

    private static function hasRecentOpenFingerprint(
        PDO $pdo,
        int $recipientId,
        ?int $sentMessageId,
        string $ip,
        string $ua
    ): bool {
        $sql = 'SELECT id FROM events
                WHERE recipient_id = :recipient_id
                  AND event_type IN ("open", "open_duplicate", "open_bot")
                  AND ip_address = :ip
                  AND user_agent = :user_agent
                  AND created_at >= (NOW() - INTERVAL 10 MINUTE)';

        if ($sentMessageId !== null) {
            $sql .= ' AND sent_message_id = :sent_message_id';
        }

        $sql .= ' ORDER BY id DESC LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':recipient_id', $recipientId, PDO::PARAM_INT);
        $stmt->bindValue(':ip', $ip);
        $stmt->bindValue(':user_agent', $ua);

        if ($sentMessageId !== null) {
            $stmt->bindValue(':sent_message_id', $sentMessageId, PDO::PARAM_INT);
        }

        $stmt->execute();
        return (bool) $stmt->fetch();
    }

    public static function trackClick(PDO $pdo, array $recipient, string $url, ?int $sentMessageId = null): void
    {
        $clickCount = (int) $recipient['click_count'] + 1;

        $stmt = $pdo->prepare(
            'UPDATE recipients SET click_count = :click_count, first_clicked_at = COALESCE(first_clicked_at, NOW()), last_clicked_at = NOW(), status = :status, updated_at = NOW() WHERE id = :id'
        );

        $stmt->execute([
            ':click_count' => $clickCount,
            ':status' => 'clicked',
            ':id' => (int) $recipient['id'],
        ]);

        self::logEvent($pdo, (int) $recipient['id'], 'click', [
            'url' => $url,
            'human_likely' => self::isHumanLikely(),
        ], $sentMessageId);
    }

    public static function markBounced(PDO $pdo, string $token, string $reason): bool
    {
        $recipient = self::findRecipientByToken($pdo, $token);
        if (!$recipient) {
            return false;
        }

        $stmt = $pdo->prepare('UPDATE recipients SET status = :status, bounced_at = NOW(), bounce_reason = :reason, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':status' => 'bounced',
            ':reason' => substr($reason, 0, 500),
            ':id' => (int) $recipient['id'],
        ]);

        self::logEvent($pdo, (int) $recipient['id'], 'bounced', ['reason' => $reason]);
        return true;
    }

    public static function getDashboardStats(PDO $pdo): array
    {
        $totals = $pdo->query(
            "SELECT
                COUNT(*) AS sent,
                SUM(CASE WHEN open_count > 0 THEN 1 ELSE 0 END) AS unique_opened,
                SUM(CASE WHEN click_count > 0 THEN 1 ELSE 0 END) AS unique_clicked,
                SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) AS bounced
             FROM recipients"
        )->fetch() ?: ['sent' => 0, 'unique_opened' => 0, 'unique_clicked' => 0, 'bounced' => 0];

        $totals['sent'] = (int) $totals['sent'];
        $totals['unique_opened'] = (int) $totals['unique_opened'];
        $totals['unique_clicked'] = (int) $totals['unique_clicked'];
        $totals['bounced'] = (int) $totals['bounced'];
        $totals['delivered_estimated'] = max(0, $totals['sent'] - $totals['bounced']);

        $totals['open_rate'] = $totals['sent'] > 0 ? round(($totals['unique_opened'] / $totals['sent']) * 100, 2) : 0.0;
        $totals['click_rate'] = $totals['sent'] > 0 ? round(($totals['unique_clicked'] / $totals['sent']) * 100, 2) : 0.0;

        return $totals;
    }

    public static function listEmailListsWithStats(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT
                el.id,
                el.name,
                el.created_at,
                (
                    SELECT COUNT(*)
                    FROM sent_messages sm
                    WHERE sm.list_id = el.id
                ) AS total_messages,
                (
                    SELECT COUNT(DISTINCT sm.recipient_id)
                    FROM sent_messages sm
                    WHERE sm.list_id = el.id
                ) AS total_recipients,
                (
                    SELECT COUNT(DISTINCT sm.recipient_id)
                    FROM sent_messages sm
                    INNER JOIN events e ON e.sent_message_id = sm.id
                    WHERE sm.list_id = el.id
                      AND e.event_type = "open"
                ) AS unique_opened,
                (
                    SELECT COUNT(DISTINCT sm.recipient_id)
                    FROM sent_messages sm
                    INNER JOIN events e ON e.sent_message_id = sm.id
                    WHERE sm.list_id = el.id
                      AND e.event_type = "click"
                ) AS unique_clicked
             FROM email_lists el
             ORDER BY el.name ASC'
        );

        $rows = $stmt->fetchAll() ?: [];

        foreach ($rows as &$row) {
            $row['total_messages'] = (int) $row['total_messages'];
            $row['total_recipients'] = (int) $row['total_recipients'];
            $row['unique_opened'] = (int) $row['unique_opened'];
            $row['unique_clicked'] = (int) $row['unique_clicked'];
            $row['open_rate'] = $row['total_recipients'] > 0 ? round(($row['unique_opened'] / $row['total_recipients']) * 100, 2) : 0.0;
            $row['click_rate'] = $row['total_recipients'] > 0 ? round(($row['unique_clicked'] / $row['total_recipients']) * 100, 2) : 0.0;
        }
        unset($row);

        return $rows;
    }

    public static function getEmailListById(PDO $pdo, int $listId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM email_lists WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $listId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listRecipientsByList(PDO $pdo, int $listId, int $limit = 300): array
    {
        $stmt = $pdo->prepare(
            'SELECT
                r.id,
                r.email,
                r.full_name,
                COUNT(DISTINCT sm.id) AS total_messages,
                COUNT(DISTINCT CASE WHEN e_open.id IS NOT NULL THEN sm.id END) AS opened_messages,
                COUNT(DISTINCT CASE WHEN e_click.id IS NOT NULL THEN sm.id END) AS clicked_messages,
                MAX(e_open.created_at) AS last_opened_at,
                MAX(e_click.created_at) AS last_clicked_at
             FROM sent_messages sm
             INNER JOIN recipients r ON r.id = sm.recipient_id
             LEFT JOIN events e_open ON e_open.sent_message_id = sm.id AND e_open.event_type = "open"
             LEFT JOIN events e_click ON e_click.sent_message_id = sm.id AND e_click.event_type = "click"
             WHERE sm.list_id = :list_id
             GROUP BY r.id, r.email, r.full_name
             ORDER BY r.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':list_id', $listId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            $row['total_messages'] = (int) $row['total_messages'];
            $row['opened_messages'] = (int) $row['opened_messages'];
            $row['clicked_messages'] = (int) $row['clicked_messages'];
        }
        unset($row);

        return $rows;
    }

    public static function listRecipients(PDO $pdo, int $limit = 200): array
    {
        $stmt = $pdo->prepare('SELECT * FROM recipients ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public static function listSentMessagesByRecipient(PDO $pdo, int $recipientId, int $limit = 100, ?int $listId = null): array
    {
        $sql =
            'SELECT sm.*, el.name AS list_name
             FROM sent_messages sm
             LEFT JOIN email_lists el ON sm.list_id = el.id
             WHERE sm.recipient_id = :recipient_id';

        if ($listId !== null) {
            $sql .= ' AND sm.list_id = :list_id';
        }

        $sql .= ' ORDER BY sm.id DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':recipient_id', $recipientId, PDO::PARAM_INT);
        if ($listId !== null) {
            $stmt->bindValue(':list_id', $listId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public static function listEventsByRecipient(PDO $pdo, int $recipientId, int $limit = 200, ?int $listId = null): array
    {
        $sql =
            'SELECT e.id, e.sent_message_id, e.event_type, e.event_data, e.ip_address, e.user_agent, e.created_at
             FROM events e';

        if ($listId !== null) {
            $sql .= ' INNER JOIN sent_messages sm ON sm.id = e.sent_message_id';
        }

        $sql .= ' WHERE e.recipient_id = :recipient_id';

        if ($listId !== null) {
            $sql .= ' AND sm.list_id = :list_id';
        }

        $sql .= ' ORDER BY e.id DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':recipient_id', $recipientId, PDO::PARAM_INT);
        if ($listId !== null) {
            $stmt->bindValue(':list_id', $listId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public static function getRecipientListStats(PDO $pdo, int $recipientId, int $listId): array
    {
        $stmt = $pdo->prepare(
            'SELECT
                COUNT(*) AS total_messages,
                SUM(CASE WHEN msg_has_open.has_open = 1 THEN 1 ELSE 0 END) AS opened_messages,
                SUM(CASE WHEN msg_has_click.has_click = 1 THEN 1 ELSE 0 END) AS clicked_messages
             FROM sent_messages sm
             LEFT JOIN (
                 SELECT sent_message_id, 1 AS has_open
                 FROM events
                 WHERE event_type = "open"
                 GROUP BY sent_message_id
             ) msg_has_open ON msg_has_open.sent_message_id = sm.id
             LEFT JOIN (
                 SELECT sent_message_id, 1 AS has_click
                 FROM events
                 WHERE event_type = "click"
                 GROUP BY sent_message_id
             ) msg_has_click ON msg_has_click.sent_message_id = sm.id
             WHERE sm.recipient_id = :recipient_id
               AND sm.list_id = :list_id'
        );
        $stmt->execute([
            ':recipient_id' => $recipientId,
            ':list_id' => $listId,
        ]);

        $stats = $stmt->fetch() ?: ['total_messages' => 0, 'opened_messages' => 0, 'clicked_messages' => 0];

        return [
            'total_messages' => (int) ($stats['total_messages'] ?? 0),
            'opened_messages' => (int) ($stats['opened_messages'] ?? 0),
            'clicked_messages' => (int) ($stats['clicked_messages'] ?? 0),
        ];
    }

    public static function clientIp(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($headers as $key) {
            if (!empty($_SERVER[$key])) {
                return trim(explode(',', (string) $_SERVER[$key])[0]);
            }
        }
        return '0.0.0.0';
    }

    public static function isHumanLikely(): bool
    {
        $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $bots = [
            'bot',
            'spider',
            'crawler',
            'scanner',
            'googleimageproxy',
            'outlook-ios',
            'proofpoint',
            'barracuda',
            'mimecast',
            'symantec',
            'trendmicro',
        ];

        foreach ($bots as $bot) {
            if ($ua !== '' && strpos($ua, $bot) !== false) {
                return false;
            }
        }

        return true;
    }

    public static function toUtcSchedule(string $localDateTime, string $timezone): ?string
    {
        $localDateTime = trim($localDateTime);
        $timezone = trim($timezone);

        if ($localDateTime === '' || $timezone === '') {
            return null;
        }

        try {
            $tz = new DateTimeZone($timezone);
            $dt = new DateTimeImmutable($localDateTime, $tz);
            return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function isOpenProxyLikely(): bool
    {
        $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $purpose = strtolower((string) ($_SERVER['HTTP_PURPOSE'] ?? ''));
        $xPurpose = strtolower((string) ($_SERVER['HTTP_X_PURPOSE'] ?? ''));
        $secPurpose = strtolower((string) ($_SERVER['HTTP_SEC_PURPOSE'] ?? ''));
        $acceptLanguage = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));

        $prefetchHints = ['prefetch', 'preview', 'prerender'];
        foreach ($prefetchHints as $hint) {
            if (
                ($purpose !== '' && strpos($purpose, $hint) !== false)
                || ($xPurpose !== '' && strpos($xPurpose, $hint) !== false)
                || ($secPurpose !== '' && strpos($secPurpose, $hint) !== false)
            ) {
                return true;
            }
        }

        $proxyAgents = [
            'googleimageproxy',
            'outlook',
            'microsoft office',
            'proofpoint',
            'mimecast',
            'barracuda',
            'symantec',
            'trendmicro',
            'urlscan',
        ];

        foreach ($proxyAgents as $needle) {
            if ($ua !== '' && strpos($ua, $needle) !== false) {
                return true;
            }
        }

        if ($ua !== '' && $acceptLanguage === '') {
            return true;
        }

        return false;
    }
}
