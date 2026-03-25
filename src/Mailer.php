<?php

declare(strict_types=1);

final class Mailer
{
    public static function sendHtml(array $config, string $toEmail, string $toName, string $subject, string $html): array
    {
        $toEmail = trim($toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid recipient email'];
        }

        $mailerConfig = $config['mailer'] ?? [];
        $fromEmail = trim((string) ($mailerConfig['from_email'] ?? ''));
        $fromName = trim((string) ($mailerConfig['from_name'] ?? ''));
        $replyTo = trim((string) ($mailerConfig['reply_to'] ?? ''));

        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Missing valid mailer.from_email in config'];
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::formatAddress($fromEmail, $fromName),
        ];

        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $headers[] = 'X-Mailer: PHP/' . PHP_VERSION;

        $toHeader = self::formatAddress($toEmail, $toName);
        $ok = @mail($toHeader, $encodedSubject, $html, implode("\r\n", $headers));
        if ($ok) {
            return ['ok' => true, 'error' => ''];
        }

        $lastError = error_get_last();
        return ['ok' => false, 'error' => (string) ($lastError['message'] ?? 'mail() failed')];
    }

    private static function formatAddress(string $email, string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return $email;
        }

        $safeName = str_replace(['"', "\r", "\n"], '', $name);
        return sprintf('"%s" <%s>', $safeName, $email);
    }
}
