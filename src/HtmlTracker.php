<?php

declare(strict_types=1);

final class HtmlTracker
{
    public static function trackedHtml(string $html, string $token, string $baseUrl, ?int $sentMessageId = null): string
    {
        $html = self::rewriteLinks($html, $token, $baseUrl, $sentMessageId);
        return self::rewriteUploadedImages($html, $token, $baseUrl, $sentMessageId);
    }

    public static function rewriteLinks(string $html, string $token, string $baseUrl, ?int $sentMessageId = null): string
    {
        return preg_replace_callback('/href\s*=\s*["\']([^"\']+)["\']/i', function (array $matches) use ($token, $baseUrl, $sentMessageId) {
            $target = trim($matches[1]);
            if ($target === '' || stripos($target, 'mailto:') === 0 || stripos($target, 'tel:') === 0 || stripos($target, '#') === 0) {
                return $matches[0];
            }

            $encodedUrl = rtrim(strtr(base64_encode($target), '+/', '-_'), '=');
            $tracked = rtrim($baseUrl, '/') . '/track/click.php?t=' . rawurlencode($token) . '&u=' . rawurlencode($encodedUrl);
            if ($sentMessageId !== null) {
                $tracked .= '&mid=' . rawurlencode((string) $sentMessageId);
            }

            return 'href="' . htmlspecialchars($tracked, ENT_QUOTES, 'UTF-8') . '"';
        }, $html) ?? $html;
    }

    public static function previewHtml(string $html, string $token, string $baseUrl, ?int $sentMessageId = null): string
    {
        // Preview keeps click tracking links and intentionally avoids image/open tracking.
        return self::rewriteLinks($html, $token, $baseUrl, $sentMessageId);
    }

    public static function rewriteUploadedImages(string $html, string $token, string $baseUrl, ?int $sentMessageId = null): string
    {
        return preg_replace_callback('/src\s*=\s*["\']([^"\']+)["\']/i', function (array $matches) use ($token, $baseUrl, $sentMessageId) {
            $source = trim($matches[1]);
            if (!self::isTrackableUploadedImage($source, $baseUrl)) {
                return $matches[0];
            }

            $encodedUrl = rtrim(strtr(base64_encode($source), '+/', '-_'), '=');
            $tracked = rtrim($baseUrl, '/') . '/track/image.php?t=' . rawurlencode($token) . '&u=' . rawurlencode($encodedUrl);
            if ($sentMessageId !== null) {
                $tracked .= '&mid=' . rawurlencode((string) $sentMessageId);
            }

            return 'src="' . htmlspecialchars($tracked, ENT_QUOTES, 'UTF-8') . '"';
        }, $html) ?? $html;
    }

    private static function isTrackableUploadedImage(string $source, string $baseUrl): bool
    {
        if ($source === '' || stripos($source, 'data:') === 0) {
            return false;
        }

        // Support absolute URL form created by the upload endpoint.
        $base = parse_url($baseUrl);
        $src = parse_url($source);

        $baseHost = strtolower((string) ($base['host'] ?? ''));
        $srcHost = strtolower((string) ($src['host'] ?? ''));
        $basePath = rtrim((string) ($base['path'] ?? ''), '/');
        $srcPath = (string) ($src['path'] ?? '');

        if ($baseHost !== '' && $srcHost !== '' && $baseHost === $srcHost) {
            return strpos($srcPath, $basePath . '/uploads/') === 0;
        }

        // Support relative forms like /email-insights/uploads/file.jpg or uploads/file.jpg.
        if (strpos($source, '/uploads/') !== false) {
            return true;
        }

        return strpos($source, 'uploads/') === 0;
    }

    public static function decodeUrl(string $safeBase64): string
    {
        $padded = strtr($safeBase64, '-_', '+/');
        $padLen = strlen($padded) % 4;
        if ($padLen > 0) {
            $padded .= str_repeat('=', 4 - $padLen);
        }

        $decoded = base64_decode($padded, true);
        return $decoded === false ? '' : $decoded;
    }
}
