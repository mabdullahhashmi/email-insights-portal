<?php

declare(strict_types=1);

final class HtmlTracker
{
    public static function trackedHtml(string $html, string $token, string $baseUrl, ?int $sentMessageId = null): string
    {
        $html = self::rewriteLinks($html, $token, $baseUrl, $sentMessageId);
        $pixel = self::pixelTag($token, $baseUrl, $sentMessageId);

        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $pixel . '</body>', $html, 1) ?? ($html . $pixel);
        }

        return $html . $pixel;
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
        // Preview keeps click tracking links but intentionally omits the open pixel.
        return self::rewriteLinks($html, $token, $baseUrl, $sentMessageId);
    }

    public static function pixelTag(string $token, string $baseUrl, ?int $sentMessageId = null): string
    {
        $url = rtrim($baseUrl, '/') . '/track/open.php?t=' . rawurlencode($token);
        if ($sentMessageId !== null) {
            $url .= '&mid=' . rawurlencode((string) $sentMessageId);
        }
        return '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="" width="1" height="1" style="display:none;" />';
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
