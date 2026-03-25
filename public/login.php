<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (Auth::login($username, $password, $config)) {
        header('Location: ' . portal_url($config, '/dashboard.php'));
        exit;
    }

    $error = 'Invalid username or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Email Insights Login</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f6f8; }
        .wrap { max-width: 420px; margin: 8vh auto; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
        input { width: 100%; box-sizing: border-box; margin: 8px 0; padding: 10px; border: 1px solid #cfd8e3; border-radius: 8px; }
        button { width: 100%; margin-top: 8px; padding: 10px; border: 0; border-radius: 8px; background: #0a66c2; color: #fff; font-weight: 600; }
        .error { color: #b00020; margin: 8px 0; }
    </style>
</head>
<body>
<div class="wrap">
    <h2>Email Insights Portal</h2>
    <?php if ($error !== ''): ?>
        <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <form method="post">
        <label>Username</label>
        <input type="text" name="username" required />
        <label>Password</label>
        <input type="password" name="password" required />
        <button type="submit">Login</button>
    </form>
</div>
</body>
</html>
