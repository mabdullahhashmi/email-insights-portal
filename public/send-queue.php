<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
Auth::requireLogin();

$pdo = Database::pdo($config);
$message = '';
$error = '';
$result = ['processed' => 0, 'sent' => 0, 'failed' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dueMessages = TrackingService::listDueScheduledMessages($pdo, 200);

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
        $message = 'No due scheduled emails right now.';
    } else {
        $message = 'Queue run completed. Processed: ' . $result['processed'] . ', sent: ' . $result['sent'] . ', failed: ' . $result['failed'];
    }
}

$pending = $pdo->query(
    "SELECT COUNT(*) AS c FROM sent_messages WHERE send_status = 'scheduled'"
)->fetch();
$pendingCount = (int) ($pending['c'] ?? 0);

$failed = $pdo->query(
    "SELECT COUNT(*) AS c FROM sent_messages WHERE send_status = 'failed'"
)->fetch();
$failedCount = (int) ($failed['c'] ?? 0);
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
    </style>
</head>
<body>
<div class="wrap">
    <p><a class="back" href="<?php echo htmlspecialchars(portal_url($config, '/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">Back to Dashboard</a></p>

    <div class="card">
        <h2 style="margin-top: 0;">Scheduled Send Queue</h2>
        <p style="color: var(--muted); margin-top: -4px;">Use this page manually, or call cron endpoint every minute to send due emails automatically.</p>

        <?php if ($message !== ''): ?><p class="ok"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

        <div class="stat-row">
            <div class="stat"><div class="label">Pending Scheduled</div><div class="value"><?php echo $pendingCount; ?></div></div>
            <div class="stat"><div class="label">Failed Sends</div><div class="value"><?php echo $failedCount; ?></div></div>
        </div>

        <form method="post">
            <button type="submit">Run Due Sends Now</button>
        </form>

        <p style="margin: 14px 0 0; color: var(--muted); font-size: 13px;">
            Cron URL format: <code>/cron-send.php?s=YOUR_WEBHOOK_SECRET</code>
        </p>
    </div>
</div>
</body>
</html>
