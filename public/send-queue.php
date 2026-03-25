<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
Auth::requireLogin();

$pdo = Database::pdo($config);
$message = '';
$error = '';
$result = ['processed' => 0, 'sent' => 0, 'failed' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dueMessages = $pdo->query(
        "SELECT sm.*, r.email AS recipient_email, r.full_name AS recipient_name, r.tracking_token
         FROM sent_messages sm
         INNER JOIN recipients r ON r.id = sm.recipient_id
         WHERE sm.send_status = 'scheduled'
         ORDER BY sm.scheduled_at_utc ASC, sm.id ASC
         LIMIT 200"
    )->fetchAll() ?: [];

    foreach ($dueMessages as $msg) {
        $result['processed']++;
        $sendResult = Mailer::sendHtml(
            $config,
            (string) ($msg['recipient_email'] ?? ''),
            (string) ($msg['recipient_name'] ?? ''),
            (string) ($msg['subject'] ?? ''),
            (string) ($msg['tracked_html'] ?? '')
        );

        if (!empty($sendResult['ok'])) {
            TrackingService::markMessageSent($pdo, (int) $msg['id']);
            TrackingService::logEvent($pdo, (int) $msg['recipient_id'], 'sent', ['source' => 'scheduled_queue'], (int) $msg['id']);
            $result['sent']++;
        } else {
            $sendError = (string) ($sendResult['error'] ?? 'Unknown send error');
            TrackingService::markMessageFailed($pdo, (int) $msg['id'], $sendError);
            TrackingService::logEvent($pdo, (int) $msg['recipient_id'], 'send_failed', ['error' => $sendError], (int) $msg['id']);
            $result['failed']++;
        }
    }

    if ($result['processed'] === 0) {
        $message = 'No scheduled emails in queue right now.';
    } else {
        $message = 'Queue run completed. Processed: ' . $result['processed'] . ', sent: ' . $result['sent'] . ', failed: ' . $result['failed'];
    }
}

$pending = $pdo->query(
    "SELECT COUNT(*) AS c FROM sent_messages WHERE send_status = 'scheduled'"
)->fetch();
$pendingCount = (int) ($pending['c'] ?? 0);

$dueNow = $pdo->query(
    "SELECT COUNT(*) AS c FROM sent_messages WHERE send_status = 'scheduled' AND scheduled_at_utc IS NOT NULL AND scheduled_at_utc <= UTC_TIMESTAMP()"
)->fetch();
$dueNowCount = (int) ($dueNow['c'] ?? 0);

$failed = $pdo->query(
    "SELECT COUNT(*) AS c FROM sent_messages WHERE send_status = 'failed'"
)->fetch();
$failedCount = (int) ($failed['c'] ?? 0);

$failedItems = $pdo->query(
    "SELECT id, recipient_email_snapshot, subject, last_error, send_attempts, updated_at
     FROM sent_messages
     WHERE send_status = 'failed'
     ORDER BY updated_at DESC, id DESC
     LIMIT 10"
)->fetchAll() ?: [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Send Queue</title>
    <style>
        :root {
            --bg: #f6f8fb;
            --line: #dbe4ef;
            --text: #0f172a;
            --muted: #64748b;
            --brand: #0f766e;
            --danger: #b91c1c;
            --ok: #166534;
        }
        body { margin: 0; font-family: "Trebuchet MS", "Lucida Sans", sans-serif; background: var(--bg); color: var(--text); }
        .wrap { max-width: 860px; margin: 22px auto; padding: 0 14px 28px; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 16px; box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05); }
        .back { color: var(--brand); text-decoration: none; font-weight: 700; }
        .stat-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin: 14px 0; }
        .stat { border: 1px solid var(--line); border-radius: 10px; padding: 10px; }
        .label { color: var(--muted); font-size: 12px; }
        .value { font-size: 22px; font-weight: 800; }
        button { border: 0; background: var(--brand); color: #fff; padding: 10px 14px; border-radius: 10px; font-weight: 700; cursor: pointer; }
        .ok { color: var(--ok); }
        .error { color: var(--danger); }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid var(--line); padding: 8px; text-align: left; vertical-align: top; font-size: 13px; }
        th { background: #f8fafc; }
    </style>
</head>
<body>
<div class="wrap">
    <p><a class="back" href="<?php echo htmlspecialchars(portal_url($config, '/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">Back to Dashboard</a></p>

    <div class="card">
        <h2 style="margin-top: 0;">Scheduled Send Queue</h2>
        <p style="color: var(--muted); margin-top: -4px;">Use this page manually, or call cron endpoint every minute to send due emails automatically.</p>
        <p style="color: var(--muted); margin-top: -8px; font-size: 13px;">Manual run sends all currently scheduled emails immediately. Cron sends only those due by UTC time.</p>

        <?php if ($message !== ''): ?><p class="ok"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

        <div class="stat-row">
            <div class="stat"><div class="label">Pending Scheduled</div><div class="value"><?php echo $pendingCount; ?></div></div>
            <div class="stat"><div class="label">Due To Send Now (UTC)</div><div class="value"><?php echo $dueNowCount; ?></div></div>
            <div class="stat"><div class="label">Failed Sends</div><div class="value"><?php echo $failedCount; ?></div></div>
        </div>

        <form method="post">
            <button type="submit">Run Due Sends Now</button>
        </form>

        <p style="margin: 14px 0 0; color: var(--muted); font-size: 13px;">
            Cron URL format: <code>/cron-send.php?s=YOUR_WEBHOOK_SECRET</code>
        </p>

        <?php if (!empty($failedItems)): ?>
            <h3 style="margin: 18px 0 8px;">Recent Failed Sends</h3>
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Recipient</th>
                    <th>Subject</th>
                    <th>Attempts</th>
                    <th>Last Error</th>
                    <th>Updated (server time)</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($failedItems as $row): ?>
                    <tr>
                        <td><?php echo (int) ($row['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['recipient_email_snapshot'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['subject'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) ($row['send_attempts'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['last_error'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['updated_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
