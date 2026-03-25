<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
Auth::requireLogin();

$pdo = Database::pdo($config);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $name = trim((string) ($_POST['full_name'] ?? ''));

    try {
        $created = TrackingService::createRecipient($pdo, $email, $name);
        if ($created === null) {
            $error = 'Invalid email address.';
        } else {
            $message = 'Recipient created successfully.';
        }
    } catch (Throwable $e) {
        $error = 'Recipient already exists or could not be created.';
    }
}

$rows = TrackingService::listRecipients($pdo, 500);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Recipients</title>
    <style>
        :root {
            --bg: #f6f8fb;
            --card: #fff;
            --line: #dbe4ef;
            --text: #0f172a;
            --muted: #64748b;
            --brand: #0f766e;
            --brand-dark: #115e59;
        }
        body { margin: 0; font-family: "Segoe UI", Tahoma, sans-serif; color: var(--text); background: var(--bg); }
        .container { max-width: 1180px; margin: 20px auto; padding: 0 14px 24px; }
        .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .top a { color: var(--brand-dark); text-decoration: none; font-weight: 700; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 14px; box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05); margin-bottom: 12px; }
        input { padding: 10px; border: 1px solid #c8d5e5; border-radius: 10px; width: 260px; margin-right: 8px; }
        button { padding: 10px 13px; border: 0; border-radius: 10px; background: var(--brand); color: #fff; font-weight: 700; }
        button:hover { background: var(--brand-dark); }
        .ok { color: #166534; }
        .error { color: #b91c1c; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid var(--line); }
        th, td { font-size: 13px; border-bottom: 1px solid #e9eef5; padding: 10px; text-align: left; }
        th { background: #f0f5fb; }
        .email-link { color: #0a4ea8; text-decoration: none; font-weight: 700; }
        .token { font-family: Consolas, monospace; font-size: 12px; color: var(--muted); }
        @media (max-width: 900px) {
            input { width: 100%; margin: 0 0 8px; }
            button { width: 100%; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="top">
        <h2 style="margin:0;">Recipients</h2>
        <a href="<?php echo htmlspecialchars(portal_url($config, '/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">Back to Dashboard</a>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">Add Recipient</h3>
        <?php if ($message !== ''): ?><p class="ok"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <form method="post">
            <input type="email" name="email" placeholder="recipient@example.com" required />
            <input type="text" name="full_name" placeholder="Full name (optional)" />
            <button type="submit">Create Recipient</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Email</th>
                <th>Name</th>
                <th>Status</th>
                <th>Opens</th>
                <th>Clicks</th>
                <th>Last Open</th>
                <th>Last Click</th>
                <th>Token</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="9">No recipients.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo (int) $r['id']; ?></td>
                    <td>
                        <a class="email-link" href="<?php echo htmlspecialchars(portal_url($config, '/recipient.php?id=' . (int) $r['id']), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars((string) $r['email'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </td>
                    <td><?php echo htmlspecialchars((string) $r['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) $r['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) $r['open_count']; ?></td>
                    <td><?php echo (int) $r['click_count']; ?></td>
                    <td><?php echo htmlspecialchars((string) ($r['last_opened_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($r['last_clicked_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="token"><?php echo htmlspecialchars((string) $r['tracking_token'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
