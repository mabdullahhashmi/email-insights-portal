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
        string $trackedHtml
    ): int {
        $stmt = $pdo->prepare(
            'INSERT INTO sent_messages (recipient_id, list_id, subject, original_html, tracked_html, created_at)
             VALUES (:recipient_id, :list_id, :subject, :original_html, :tracked_html, NOW())'
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
        $stmt->execute();

        return (int) $pdo->lastInsertId();
    }

    public static function updateSentMessageTrackedHtml(PDO $pdo, int $sentMessageId, string $trackedHtml): void
    {
        $stmt = $pdo->prepare('UPDATE sent_messages SET tracked_html = :tracked_html WHERE id = :id');
        $stmt->execute([
            ':tracked_html' => $trackedHtml,
            ':id' => $sentMessageId,
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
        $duplicateLikely = self::hasRecentOpenFingerprint($pdo, $recipientId, $sentMessageId, $ip, $ua);

        $shouldIncrement = $humanLikely && !$duplicateLikely;

        if ($shouldIncrement) {
            $openCount = (int) $recipient['open_count'] + 1;
            $firstOpenedAt = $recipient['first_opened_at'] ?: date('Y-m-d H:i:s');

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
        if (!$humanLikely) {
            $eventType = 'open_bot';
        } elseif ($duplicateLikely) {
            $eventType = 'open_duplicate';
        }

        self::logEvent($pdo, $recipientId, $eventType, [
            'source' => 'pixel',
            'human_likely' => $humanLikely,
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

    public static function listRecipients(PDO $pdo, int $limit = 200): array
    {
        $stmt = $pdo->prepare('SELECT * FROM recipients ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public static function listSentMessagesByRecipient(PDO $pdo, int $recipientId, int $limit = 100): array
    {
        $stmt = $pdo->prepare(
            'SELECT sm.*, el.name AS list_name
             FROM sent_messages sm
             LEFT JOIN email_lists el ON sm.list_id = el.id
             WHERE sm.recipient_id = :recipient_id
             ORDER BY sm.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':recipient_id', $recipientId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public static function listEventsByRecipient(PDO $pdo, int $recipientId, int $limit = 200): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, sent_message_id, event_type, event_data, ip_address, user_agent, created_at
             FROM events
             WHERE recipient_id = :recipient_id
             ORDER BY id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':recipient_id', $recipientId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
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
        $bots = ['bot', 'spider', 'crawler', 'scanner', 'googleimageproxy', 'outlook-ios'];

        foreach ($bots as $bot) {
            if ($ua !== '' && strpos($ua, $bot) !== false) {
                return false;
            }
        }

        return true;
    }
}
