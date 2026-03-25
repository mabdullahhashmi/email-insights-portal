<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
Auth::requireLogin();

$pdo = Database::pdo($config);
$stats = TrackingService::getDashboardStats($pdo);
$rows = TrackingService::listRecipients($pdo, 300);
$lists = TrackingService::listEmailLists($pdo);

$listCountStmt = $pdo->query('SELECT COUNT(*) AS c FROM sent_messages');
$totalMessages = (int) (($listCountStmt->fetch()['c'] ?? 0));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Email Insights Dashboard</title>
    <style>
        :root {
            --bg: #f5f7fb;
            --text: #0f172a;
            --muted: #64748b;
            --card: #ffffff;
            --line: #dae3ef;
            --brand: #0f766e;
            --brand-dark: #115e59;
            --accent: #0369a1;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 0% 0%, #d1fae5 0%, transparent 35%),
                radial-gradient(circle at 100% 0%, #dbeafe 0%, transparent 25%),
                var(--bg);
        }
        .container { max-width: 1260px; margin: 20px auto; padding: 0 14px 30px; }
        .bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .bar h1 { margin: 0; font-size: 30px; }
        .bar p { margin: 5px 0 0; color: var(--muted); }
        .links a {
            margin-right: 10px;
            text-decoration: none;
            color: #fff;
            background: var(--brand);
            padding: 9px 12px;
            border-radius: 10px;
            font-weight: 700;
            display: inline-block;
        }
        .links a:hover { background: var(--brand-dark); }
        .grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }
        .label { color: var(--muted); font-size: 12px; }
        .value { font-size: 22px; font-weight: 800; margin-top: 2px; }
        .layout { display: grid; grid-template-columns: 2fr 1fr; gap: 12px; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--line);
        }
        th, td { font-size: 13px; border-bottom: 1px solid #e7edf6; padding: 10px; text-align: left; }
        th { background: #edf3fb; }
        .email-link { color: var(--accent); text-decoration: none; font-weight: 700; }
        .list-chip {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #075985;
            font-size: 12px;
            margin: 0 6px 6px 0;
        }
        @media (max-width: 1100px) {
            .grid { grid-template-columns: repeat(2, 1fr); }
            .layout { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="bar">
        <div>
            <h1>Email Insights</h1>
            <p>Recipient-first analytics for manually sent webmail HTML emails.</p>
        </div>
        <div class="links">
            <a href="<?php echo htmlspecialchars(portal_url($config, '/recipients.php'), ENT_QUOTES, 'UTF-8'); ?>">Recipients</a>
            <a href="<?php echo htmlspecialchars(portal_url($config, '/generate.php'), ENT_QUOTES, 'UTF-8'); ?>">Generate Email</a>
            <a href="<?php echo htmlspecialchars(portal_url($config, '/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Logout</a>
        </div>
    </div>

    <div class="grid">
        <div class="card"><div class="label">Recipients</div><div class="value"><?php echo (int) $stats['sent']; ?></div></div>
        <div class="card"><div class="label">Tracked Emails</div><div class="value"><?php echo $totalMessages; ?></div></div>
        <div class="card"><div class="label">Delivered Est.</div><div class="value"><?php echo (int) $stats['delivered_estimated']; ?></div></div>
        <div class="card"><div class="label">Unique Opened</div><div class="value"><?php echo (int) $stats['unique_opened']; ?></div></div>
        <div class="card"><div class="label">Unique Clicked</div><div class="value"><?php echo (int) $stats['unique_clicked']; ?></div></div>
        <div class="card"><div class="label">Open Rate</div><div class="value"><?php echo number_format((float) $stats['open_rate'], 2); ?>%</div></div>
        <div class="card"><div class="label">Click Rate</div><div class="value"><?php echo number_format((float) $stats['click_rate'], 2); ?>%</div></div>
    </div>

    <div class="layout">
        <div class="card">
            <h3 style="margin-top:0;">Recipients Performance</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Opens</th>
                        <th>Clicks</th>
                        <th>Last Open</th>
                        <th>Last Click</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7">No recipients found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo (int) $r['id']; ?></td>
                            <td>
                                <a class="email-link" href="<?php echo htmlspecialchars(portal_url($config, '/recipient.php?id=' . (int) $r['id']), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars((string) $r['email'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars((string) $r['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) $r['open_count']; ?></td>
                            <td><?php echo (int) $r['click_count']; ?></td>
                            <td><?php echo htmlspecialchars((string) ($r['last_opened_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r['last_clicked_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Email Lists</h3>
            <?php if (!$lists): ?>
                <p style="color: var(--muted);">No lists yet. Create one in Generate Email.</p>
            <?php else: ?>
                <?php foreach ($lists as $list): ?>
                    <span class="list-chip"><?php echo htmlspecialchars((string) $list['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
            <hr style="border:0;border-top:1px solid var(--line); margin:14px 0;" />
            <p style="margin:0; color: var(--muted); font-size: 13px;">
                Tip: click any recipient email to see all previously generated emails, HTML previews, and event timeline.
            </p>
        </div>
    </div>
</div>
</body>
</html>
