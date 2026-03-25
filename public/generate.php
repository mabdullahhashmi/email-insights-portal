<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
Auth::requireLogin();

$pdo = Database::pdo($config);
$recipients = TrackingService::listRecipients($pdo, 500);
$lists = TrackingService::listEmailLists($pdo);

$timezoneOptions = ['UTC'];
$defaultTimezone = 'UTC';

$trackedHtml = '';
$previewHtml = '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim((string) ($_POST['token'] ?? ''));
    $recipientEmail = trim((string) ($_POST['recipient_email'] ?? ''));
    $recipientName = trim((string) ($_POST['recipient_name'] ?? ''));
    $listId = (int) ($_POST['list_id'] ?? 0);
    $newListName = trim((string) ($_POST['new_list_name'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $html = (string) ($_POST['html_content'] ?? '');
    $sendMode = (string) ($_POST['send_mode'] ?? 'generate');
    $scheduleLocal = trim((string) ($_POST['schedule_local'] ?? ''));
    $scheduleTimezone = trim((string) ($_POST['schedule_timezone'] ?? $defaultTimezone));

    if (!in_array($sendMode, ['generate', 'send_now', 'schedule'], true)) {
        $sendMode = 'generate';
    }

    if (!in_array($scheduleTimezone, $timezoneOptions, true)) {
        $scheduleTimezone = $defaultTimezone;
    }

    if (trim($html) === '') {
        $error = 'HTML content is required.';
    } else {
        $recipient = null;

        if ($token !== '') {
            $recipient = TrackingService::findRecipientByToken($pdo, $token);
            if (!$recipient) {
                $error = 'Selected recipient token is not valid.';
            }
        } elseif ($recipientEmail !== '') {
            $recipient = TrackingService::getOrCreateRecipient($pdo, $recipientEmail, $recipientName);
            if (!$recipient) {
                $error = 'Recipient email is invalid.';
            }
        } else {
            $error = 'Select a recipient token or enter recipient email.';
        }

        $selectedListId = null;
        if ($error === '') {
            if ($newListName !== '') {
                $list = TrackingService::getOrCreateEmailList($pdo, $newListName);
                if ($list) {
                    $selectedListId = (int) $list['id'];
                }
            } elseif ($listId > 0) {
                $selectedListId = $listId;
            }
        }

        if ($error === '' && $recipient) {
            $recipientId = (int) $recipient['id'];
            $recipientToken = (string) $recipient['tracking_token'];
            $baseUrl = (string) ($config['base_url'] ?? '');

            $sendStatus = 'generated';
            $scheduledUtc = null;

            if ($sendMode === 'schedule') {
                $scheduledUtc = TrackingService::toUtcSchedule($scheduleLocal, $scheduleTimezone);
                if ($scheduledUtc === null) {
                    $error = 'Schedule date/time or timezone is invalid.';
                } else {
                    $sendStatus = 'scheduled';
                }
            } elseif ($sendMode === 'send_now') {
                $sendStatus = 'queued';
            }

            if ($error !== '') {
                $previewHtml = HtmlTracker::previewHtml($html, $recipientToken, $baseUrl, null);
                goto render_page;
            }

            $sentMessageId = TrackingService::createSentMessage(
                $pdo,
                $recipientId,
                $selectedListId,
                $subject,
                $html,
                '',
                $sendStatus,
                $scheduledUtc,
                $scheduleTimezone
            );

            $trackedHtml = HtmlTracker::trackedHtml($html, $recipientToken, $baseUrl, $sentMessageId);
            $previewHtml = HtmlTracker::previewHtml($html, $recipientToken, $baseUrl, $sentMessageId);
            TrackingService::updateSentMessageTrackedHtml($pdo, $sentMessageId, $trackedHtml);
            TrackingService::logEvent(
                $pdo,
                $recipientId,
                'generated',
                [
                    'source' => 'generate_page',
                    'list_id' => $selectedListId,
                ],
                $sentMessageId
            );

            if ($sendMode === 'send_now') {
                $sendResult = Mailer::sendHtml(
                    $config,
                    (string) ($recipient['email'] ?? ''),
                    (string) ($recipient['full_name'] ?? ''),
                    $subject,
                    $trackedHtml
                );

                if (!empty($sendResult['ok'])) {
                    TrackingService::markMessageSent($pdo, $sentMessageId);
                    TrackingService::logEvent($pdo, $recipientId, 'sent', ['source' => 'portal_send_now'], $sentMessageId);
                    $success = 'Email sent immediately and tracked. Message ID: ' . $sentMessageId;
                } else {
                    $sendError = (string) ($sendResult['error'] ?? 'Unknown send error');
                    TrackingService::markMessageFailed($pdo, $sentMessageId, $sendError);
                    TrackingService::logEvent($pdo, $recipientId, 'send_failed', ['error' => $sendError], $sentMessageId);
                    $error = 'Send failed: ' . $sendError;
                }
            } elseif ($sendMode === 'schedule') {
                $success = 'Email scheduled successfully. Message ID: ' . $sentMessageId . ' (scheduled in ' . $scheduleTimezone . ')';
            } else {
                $success = 'Tracked email generated and saved to history. Message ID: ' . $sentMessageId;
            }

            $lists = TrackingService::listEmailLists($pdo);
            $recipients = TrackingService::listRecipients($pdo, 500);
        }
    }
}

render_page:
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Generate Tracked Email</title>
    <style>
        :root {
            --bg: #f6f8fb;
            --panel: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --brand: #0f766e;
            --brand-dark: #115e59;
            --line: #dbe3ee;
            --danger: #b91c1c;
            --ok: #166534;
            --ink-2: #102f44;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Trebuchet MS", "Lucida Sans", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 10% 10%, #d1fae5 0%, transparent 40%),
                radial-gradient(circle at 90% 10%, #e0f2fe 0%, transparent 32%),
                var(--bg);
        }
        .container { max-width: 1320px; margin: 22px auto; padding: 0 14px 30px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .topbar a { text-decoration: none; color: var(--brand-dark); font-weight: 700; }
        .title { margin: 0; font-size: 28px; }
        .muted { color: var(--muted); margin: 4px 0 0; }
        .grid { display: grid; grid-template-columns: 1.25fr 1fr; gap: 14px; }
        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.06);
            padding: 16px;
        }
        label { display: block; font-weight: 600; margin-top: 10px; }
        input, select, textarea {
            width: 100%;
            margin-top: 6px;
            padding: 10px;
            border-radius: 10px;
            border: 1px solid #c8d5e5;
            background: #fff;
        }
        textarea { min-height: 260px; font-family: Consolas, monospace; font-size: 12px; }
        .tox-tinymce { border-radius: 10px !important; border-color: #c8d5e5 !important; margin-top: 8px; }
        button {
            margin-top: 14px;
            border: 0;
            border-radius: 10px;
            padding: 11px 14px;
            background: var(--brand);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        button:hover { background: var(--brand-dark); }
        .msg { padding: 10px 12px; border-radius: 10px; margin-bottom: 10px; }
        .msg.error { background: #fee2e2; color: var(--danger); border: 1px solid #fecaca; }
        .msg.ok { background: #dcfce7; color: var(--ok); border: 1px solid #bbf7d0; }
        .preview {
            min-height: 440px;
            width: 100%;
            border: 1px solid #d1ddeb;
            border-radius: 12px;
            background: #fff;
        }
        .section-title { margin: 0 0 8px; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .small { font-size: 12px; color: var(--muted); }
        .mode-wrap { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 8px; }
        .mode-wrap label {
            border: 1px solid #c8d5e5;
            border-radius: 10px;
            padding: 10px;
            cursor: pointer;
            margin-top: 0;
            font-weight: 700;
            background: #f9fbff;
        }
        .mode-wrap input { width: auto; margin-right: 6px; }
        .hidden { display: none !important; }
        @media (max-width: 1000px) {
            .grid { grid-template-columns: 1fr; }
            .two-col { grid-template-columns: 1fr; }
            .mode-wrap { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <div>
            <h1 class="title">Generate Tracked Email</h1>
            <p class="muted">Create list or recipient inline, save sent HTML history, and preview before sending from webmail.</p>
        </div>
        <a href="<?php echo htmlspecialchars(portal_url($config, '/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">Back to Dashboard</a>
    </div>

    <?php if ($error !== ''): ?><div class="msg error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="msg ok"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <div class="grid">
        <div class="card">
            <h3 class="section-title">1) Recipient + List + Compose</h3>
            <form method="post">
                <div class="two-col">
                    <div>
                        <label>Select Existing Recipient</label>
                        <select name="token">
                            <option value="">Choose existing recipient (optional)</option>
                            <?php foreach ($recipients as $r): ?>
                                <option value="<?php echo htmlspecialchars((string) $r['tracking_token'], ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo ((string) ($_POST['token'] ?? '') === (string) $r['tracking_token']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $r['email'] . ' | ' . (string) $r['tracking_token'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="small">If selected, email fields below are ignored.</p>
                    </div>
                    <div>
                        <label>Or Create/Use by Email</label>
                        <input type="email" name="recipient_email" placeholder="recipient@example.com" value="<?php echo htmlspecialchars((string) ($_POST['recipient_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                        <input type="text" name="recipient_name" placeholder="Recipient name" value="<?php echo htmlspecialchars((string) ($_POST['recipient_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                </div>

                <div class="two-col">
                    <div>
                        <label>Select Existing List</label>
                        <select name="list_id">
                            <option value="0">No list</option>
                            <?php foreach ($lists as $list): ?>
                                <option value="<?php echo (int) $list['id']; ?>"
                                    <?php echo ((int) ($_POST['list_id'] ?? 0) === (int) $list['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $list['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Or Create New List</label>
                        <input type="text" name="new_list_name" placeholder="welcome, followup, promo, etc" value="<?php echo htmlspecialchars((string) ($_POST['new_list_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                </div>

                <label>Send Mode</label>
                <div class="mode-wrap">
                    <label><input type="radio" name="send_mode" value="generate" <?php echo ((string) ($_POST['send_mode'] ?? 'generate') === 'generate') ? 'checked' : ''; ?> /> Generate only</label>
                    <label><input type="radio" name="send_mode" value="send_now" <?php echo ((string) ($_POST['send_mode'] ?? '') === 'send_now') ? 'checked' : ''; ?> /> Send now</label>
                    <label><input type="radio" name="send_mode" value="schedule" <?php echo ((string) ($_POST['send_mode'] ?? '') === 'schedule') ? 'checked' : ''; ?> /> Schedule send</label>
                </div>

                <div class="two-col" id="schedule-fields">
                    <div>
                        <label>Schedule Date/Time</label>
                        <input type="datetime-local" name="schedule_local" value="<?php echo htmlspecialchars((string) ($_POST['schedule_local'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div>
                        <label>Timezone</label>
                        <select name="schedule_timezone">
                            <?php foreach ($timezoneOptions as $tz): ?>
                                <option value="<?php echo htmlspecialchars($tz, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ((string) ($_POST['schedule_timezone'] ?? $defaultTimezone) === $tz) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tz, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label>Email Subject (saved in history)</label>
                <input type="text" name="subject" value="<?php echo htmlspecialchars((string) ($_POST['subject'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Optional but recommended" />

                <label>Email Composer</label>
                <textarea id="html-source" name="html_content" placeholder="Paste full HTML email..." required><?php echo htmlspecialchars((string) ($_POST['html_content'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <p class="small">Use Insert > Image to upload directly from your computer. Any links, including image links, will be auto-rewritten to click tracking URLs.</p>

                <button type="submit">Process Email</button>
            </form>
        </div>

        <div class="card">
            <h3 class="section-title">2) Preview</h3>
            <?php if ($previewHtml !== ''): ?>
                <iframe class="preview" srcdoc="<?php echo htmlspecialchars($previewHtml, ENT_QUOTES, 'UTF-8'); ?>"></iframe>
            <?php else: ?>
                <p class="small">Preview uses safe mode (no pixel) so internal preview will not create fake open events.</p>
                <div class="preview"></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($trackedHtml !== ''): ?>
        <div class="card" style="margin-top: 14px;">
            <h3 class="section-title">3) Tracked HTML Output</h3>
            <p class="small">Copy this for external email tools, or use Send Now / Schedule in this portal.</p>
            <textarea readonly><?php echo htmlspecialchars($trackedHtml, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    (function () {
        const source = document.getElementById('html-source');
        const sendModeInputs = Array.from(document.querySelectorAll('input[name="send_mode"]'));
        const scheduleFields = document.getElementById('schedule-fields');

        tinymce.init({
            selector: '#html-source',
            menubar: 'file edit insert view format table tools',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | outdent indent | numlist bullist | link image table | forecolor backcolor removeformat | code',
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table wordcount',
            height: 520,
            branding: false,
            automatic_uploads: true,
            file_picker_types: 'image',
            images_upload_url: '<?php echo htmlspecialchars(portal_url($config, '/upload-image.php'), ENT_QUOTES, 'UTF-8'); ?>',
            convert_urls: false,
            content_style: 'body { font-family: Arial, Helvetica, sans-serif; font-size:14px }',
        });

        function updateScheduleVisibility() {
            const selected = sendModeInputs.find((i) => i.checked);
            const isSchedule = selected && selected.value === 'schedule';
            scheduleFields.classList.toggle('hidden', !isSchedule);
        }

        sendModeInputs.forEach((input) => input.addEventListener('change', updateScheduleVisibility));

        updateScheduleVisibility();
    })();
</script>
</body>
</html>
