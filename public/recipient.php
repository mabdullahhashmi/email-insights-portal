<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
Auth::requireLogin();

$pdo = Database::pdo($config);
$recipientId = (int) ($_GET['id'] ?? 0);
$recipient = $recipientId > 0 ? TrackingService::findRecipientById($pdo, $recipientId) : null;

if (!$recipient) {
    http_response_code(404);
    echo 'Recipient not found.';
    exit;
}

$messages = TrackingService::listSentMessagesByRecipient($pdo, (int) $recipient['id'], 100);
$events = TrackingService::listEventsByRecipient($pdo, (int) $recipient['id'], 200);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Recipient Details</title>
    <style>
        :root {
            --bg: #f5f7fb;
            --line: #dbe3ef;
            --text: #0f172a;
            --muted: #64748b;
            --card: #fff;
            --brand: #0f766e;
        }
        body { margin: 0; font-family: "Segoe UI", Tahoma, sans-serif; background: var(--bg); color: var(--text); }
        .container { max-width: 1250px; margin: 20px auto; padding: 0 14px 30px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 14px; margin-bottom: 12px; }
        .back { color: var(--brand); text-decoration: none; font-weight: 700; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 12px; }
        .stat { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 10px; }
        .label { color: var(--muted); font-size: 12px; }
        .value { font-size: 20px; font-weight: 800; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; }
        th, td { font-size: 13px; border-bottom: 1px solid #e8edf5; padding: 10px; text-align: left; vertical-align: top; }
        th { background: #edf3fb; }
        .preview { width: 100%; min-height: 230px; border: 1px solid #d8e1ee; border-radius: 10px; }
        .code { font-family: Consolas, monospace; white-space: pre-wrap; word-break: break-word; font-size: 12px; }
        @media (max-width: 1000px) { .stats { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
<div class="container">
    <p><a class="back" href="<?php echo htmlspecialchars(portal_url($config, '/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">Back to Dashboard</a></p>

    <div class="card">
        <h2 style="margin:0;">Recipient: <?php echo htmlspecialchars((string) $recipient['email'], ENT_QUOTES, 'UTF-8'); ?></h2>
        <p style="margin:6px 0 0; color: var(--muted);">Token: <?php echo htmlspecialchars((string) $recipient['tracking_token'], ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="stats">
            <div class="stat"><div class="label">Status</div><div class="value"><?php echo htmlspecialchars((string) $recipient['status'], ENT_QUOTES, 'UTF-8'); ?></div></div>
            <div class="stat"><div class="label">Open Count</div><div class="value"><?php echo (int) $recipient['open_count']; ?></div></div>
            <div class="stat"><div class="label">Click Count</div><div class="value"><?php echo (int) $recipient['click_count']; ?></div></div>
            <div class="stat"><div class="label">Last Click</div><div class="value" style="font-size:14px;"><?php echo htmlspecialchars((string) ($recipient['last_clicked_at'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div></div>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">Past Emails (Generated HTML History)</h3>
        <?php if (!$messages): ?>
            <p style="color: var(--muted);">No tracked emails generated yet for this recipient.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>List</th>
                        <th>Subject</th>
                        <th>Created</th>
                        <th>Preview</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($messages as $m): ?>
                    <tr>
                        <td><?php echo (int) $m['id']; ?></td>
                        <td><?php echo htmlspecialchars((string) ($m['list_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($m['subject'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $m['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php if (trim((string) ($m['tracked_html'] ?? '')) !== ''): ?>
                                <iframe class="preview" srcdoc="<?php echo htmlspecialchars((string) $m['tracked_html'], ENT_QUOTES, 'UTF-8'); ?>"></iframe>
                            <?php else: ?>
                                <span style="color: var(--muted);">No HTML</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">Event Timeline</h3>
        <?php if (!$events): ?>
            <p style="color: var(--muted);">No events recorded yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Message ID</th>
                        <th>Type</th>
                        <th>Data</th>
                        <th>IP</th>
                        <th>User Agent</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($events as $e): ?>
                    <tr>
                        <td><?php echo (int) $e['id']; ?></td>
                        <td><?php echo htmlspecialchars((string) ($e['sent_message_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $e['event_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="code"><?php echo htmlspecialchars((string) $e['event_data'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $e['ip_address'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="code"><?php echo htmlspecialchars((string) $e['user_agent'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $e['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
