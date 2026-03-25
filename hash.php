<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');

    if ($password === '') {
        $error = 'Password is required.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Password Hash Generator</title>
    <style>
        body { margin: 0; background: #f4f6f8; font-family: Arial, sans-serif; }
        .wrap { max-width: 560px; margin: 8vh auto; background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        input, textarea { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #cfd8e3; border-radius: 8px; margin-top: 6px; }
        textarea { min-height: 90px; font-family: Consolas, monospace; }
        button { margin-top: 10px; border: 0; border-radius: 8px; background: #0f766e; color: #fff; padding: 10px 12px; font-weight: 700; }
        .error { color: #b00020; }
        .warn { color: #9a3412; font-size: 13px; }
    </style>
</head>
<body>
<div class="wrap">
    <h2 style="margin-top:0;">Admin Password Hash Generator</h2>
    <p class="warn">Security warning: generate hash, copy it to config, then delete this file immediately.</p>

    <?php if (!empty($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form method="post">
        <label>New Password</label>
        <input type="password" name="password" required />
        <button type="submit">Generate Hash</button>
    </form>

    <?php if (!empty($hash)): ?>
        <label style="display:block;margin-top:12px;">Password Hash</label>
        <textarea readonly><?php echo htmlspecialchars($hash, ENT_QUOTES, 'UTF-8'); ?></textarea>
    <?php endif; ?>
</div>
</body>
</html>
