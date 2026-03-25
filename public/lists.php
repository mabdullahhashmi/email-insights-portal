<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
Auth::requireLogin();

$pdo = Database::pdo($config);
$lists = TrackingService::listEmailListsWithStats($pdo);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Email Lists</title>
    <style>
        :root {
            --bg: #f6f8fb;
            --line: #dbe4ef;
            --text: #0f172a;
            --muted: #64748b;
            --brand: #0f766e;
            --brand-2: #0c4a6e;
        }
        body {
            margin: 0;
            font-family: "Trebuchet MS", "Lucida Sans", sans-serif;
            background:
                radial-gradient(circle at 5% 5%, #d1fae5 0%, transparent 35%),
                radial-gradient(circle at 95% 0%, #dbeafe 0%, transparent 35%),
                var(--bg);
            color: var(--text);
        }
        .wrap { max-width: 1220px; margin: 20px auto; padding: 0 14px 28px; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
        .top a { color: #fff; background: var(--brand); text-decoration: none; padding: 10px 12px; border-radius: 10px; font-weight: 700; }
        .top a:hover { background: #115e59; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 12px; box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05); }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e9eef5; padding: 11px; font-size: 13px; text-align: left; }
        th { background: #eff5fc; }
        .list-link { color: var(--brand-2); text-decoration: none; font-weight: 800; }
        .muted { color: var(--muted); }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h2 style="margin:0;">Email Lists</h2>
            <p class="muted" style="margin:6px 0 0;">All stats here are scoped per list, not mixed with other lists.</p>
        </div>
        <a href="<?php echo htmlspecialchars(portal_url($config, '/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">Back to Dashboard</a>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>List</th>
                    <th>Recipients</th>
                    <th>Messages</th>
                    <th>Unique Opened</th>
                    <th>Unique Clicked</th>
                    <th>Open Rate</th>
                    <th>Click Rate</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$lists): ?>
                <tr><td colspan="7">No lists available yet.</td></tr>
            <?php else: ?>
                <?php foreach ($lists as $list): ?>
                    <tr>
                        <td>
                            <a class="list-link" href="<?php echo htmlspecialchars(portal_url($config, '/list.php?id=' . (int) $list['id']), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars((string) $list['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </td>
                        <td><?php echo (int) $list['total_recipients']; ?></td>
                        <td><?php echo (int) $list['total_messages']; ?></td>
                        <td><?php echo (int) $list['unique_opened']; ?></td>
                        <td><?php echo (int) $list['unique_clicked']; ?></td>
                        <td><?php echo number_format((float) $list['open_rate'], 2); ?>%</td>
                        <td><?php echo number_format((float) $list['click_rate'], 2); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
