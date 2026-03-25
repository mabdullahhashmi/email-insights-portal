<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
Auth::requireLogin();

$pdo = Database::pdo($config);
$listId = (int) ($_GET['id'] ?? 0);
$list = $listId > 0 ? TrackingService::getEmailListById($pdo, $listId) : null;

if (!$list) {
    http_response_code(404);
    echo 'List not found.';
    exit;
}

$rows = TrackingService::listRecipientsByList($pdo, $listId, 500);
$allListStats = TrackingService::listEmailListsWithStats($pdo);
$currentStats = null;
foreach ($allListStats as $statsRow) {
    if ((int) $statsRow['id'] === $listId) {
        $currentStats = $statsRow;
        break;
    }
}

if ($currentStats === null) {
    $currentStats = [
        'total_recipients' => 0,
        'total_messages' => 0,
        'unique_opened' => 0,
        'unique_clicked' => 0,
        'open_rate' => 0,
        'click_rate' => 0,
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>List Details</title>
    <style>
        :root {
            --bg: #f6f8fb;
            --line: #dbe4ef;
            --text: #0f172a;
            --muted: #64748b;
            --brand: #0f766e;
        }
        body { margin: 0; font-family: "Trebuchet MS", "Lucida Sans", sans-serif; background: var(--bg); color: var(--text); }
        .wrap { max-width: 1240px; margin: 20px auto; padding: 0 14px 30px; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 14px; box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05); margin-bottom: 12px; }
        .back { color: var(--brand); text-decoration: none; font-weight: 700; }
        .stats { display: grid; grid-template-columns: repeat(6, 1fr); gap: 9px; }
        .stat { border: 1px solid var(--line); border-radius: 10px; padding: 10px; }
        .label { color: var(--muted); font-size: 12px; }
        .value { font-weight: 800; font-size: 20px; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e9eef5; padding: 10px; font-size: 13px; text-align: left; }
        th { background: #eff5fc; }
        .recipient-link { color: #0c4a6e; text-decoration: none; font-weight: 800; }
        @media (max-width: 1000px) { .stats { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
<div class="wrap">
    <p>
        <a class="back" href="<?php echo htmlspecialchars(portal_url($config, '/lists.php'), ENT_QUOTES, 'UTF-8'); ?>">Back to Lists</a>
        |
        <a class="back" href="<?php echo htmlspecialchars(portal_url($config, '/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">Dashboard</a>
    </p>

    <div class="card">
        <h2 style="margin:0;">List: <?php echo htmlspecialchars((string) $list['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
        <p style="color: var(--muted); margin: 5px 0 0;">Recipient and engagement stats below are scoped only to this list.</p>
        <div class="stats">
            <div class="stat"><div class="label">Recipients</div><div class="value"><?php echo (int) $currentStats['total_recipients']; ?></div></div>
            <div class="stat"><div class="label">Messages</div><div class="value"><?php echo (int) $currentStats['total_messages']; ?></div></div>
            <div class="stat"><div class="label">Unique Opened</div><div class="value"><?php echo (int) $currentStats['unique_opened']; ?></div></div>
            <div class="stat"><div class="label">Unique Clicked</div><div class="value"><?php echo (int) $currentStats['unique_clicked']; ?></div></div>
            <div class="stat"><div class="label">Open Rate</div><div class="value"><?php echo number_format((float) $currentStats['open_rate'], 2); ?>%</div></div>
            <div class="stat"><div class="label">Click Rate</div><div class="value"><?php echo number_format((float) $currentStats['click_rate'], 2); ?>%</div></div>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">Recipients In This List</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Name</th>
                    <th>Messages</th>
                    <th>Opened Messages</th>
                    <th>Clicked Messages</th>
                    <th>Last Open</th>
                    <th>Last Click</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8">No recipients found in this list yet.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo (int) $row['id']; ?></td>
                        <td>
                            <a class="recipient-link" href="<?php echo htmlspecialchars(portal_url($config, '/recipient.php?id=' . (int) $row['id'] . '&list_id=' . $listId), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars((string) $row['email'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars((string) $row['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $row['total_messages']; ?></td>
                        <td><?php echo (int) $row['opened_messages']; ?></td>
                        <td><?php echo (int) $row['clicked_messages']; ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['last_opened_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['last_clicked_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
