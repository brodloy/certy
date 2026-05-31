<?php
/**
 * FAVICON CONTROLLER — a tiny same-origin proxy for target favicons.
 *
 * Why this exists: the views want to show each host's favicon. Fetching it
 * straight from a third party (e.g. google.com/s2/favicons) has two problems —
 * privacy blockers like Brave/uBlock block that URL client-side, and it tells
 * the third party which hosts each user monitors. So instead the browser asks
 * US (same origin, never blocked), our server fetches once from Google, caches
 * the bytes on disk, and serves them. The user's browser never talks to Google.
 *
 * No SSRF risk: we only ever connect to a fixed www.google.com URL; the host is
 * just a query-string value passed to Google, never a destination we connect to.
 */
class FaviconController
{
    /** How long a cached icon is considered fresh (30 days). */
    private const TTL = 2592000;

    /** GET /favicon?host=example.com&sz=32 — serve a cached/proxied favicon. */
    public function show(): string
    {
        require_login();

        $host = $this->cleanHost(input('host'));
        if ($host === '') {
            return $this->miss();
        }

        $size  = (int) input('sz', '32');
        $size  = $size >= 16 && $size <= 128 ? $size : 32;
        $cache = $this->cachePath($host, $size);

        $bytes = $this->fromCache($cache);
        if ($bytes === null) {
            $bytes = $this->fetch($host, $size);
            if ($bytes !== null) {
                $this->store($cache, $bytes);
            }
        }

        if ($bytes === null || $bytes === '') {
            return $this->miss();
        }

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=' . self::TTL);
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    // --- helpers -------------------------------------------------------------

    /** A fresh cached copy, or null if absent/stale. */
    private function fromCache(string $path): ?string
    {
        if (!is_file($path) || (time() - (int) @filemtime($path)) > self::TTL) {
            return null;
        }
        $bytes = @file_get_contents($path);
        return $bytes === false ? null : $bytes;
    }

    /** Persist the icon bytes to the cache (best effort; never throws). */
    private function store(string $path, string $bytes): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($path, $bytes, LOCK_EX);
    }

    /**
     * Fetch the favicon from Google's S2 service. Tries cURL first, then falls
     * back to the stream wrapper — on Windows/MAMP cURL often has no CA bundle
     * and fails HTTPS verification, whereas the stream wrapper (openssl) works.
     * Returns the bytes only if they actually look like an image.
     */
    private function fetch(string $host, int $size): ?string
    {
        // Google serves crisper icons at 2× the display size.
        $url = 'https://www.google.com/s2/favicons?sz=' . ($size * 2)
            . '&domain=' . rawurlencode($host);

        $body = $this->fetchViaCurl($url) ?? $this->fetchViaStream($url);

        return ($body !== null && $this->looksLikeImage($body)) ? $body : null;
    }

    private function fetchViaCurl(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_USERAGENT      => 'certy favicon proxy',
        ]);
        // Google 301-redirects, and serves a generic fallback icon with a 404
        // for domains it has no real favicon for — so we don't require a 200.
        // looksLikeImage() (in fetch) rejects any non-image error body.
        $body = curl_exec($ch);
        curl_close($ch);
        return ($body === false || $body === '') ? null : $body;
    }

    private function fetchViaStream(string $url): ?string
    {
        $ctx = stream_context_create(['http' => [
            'timeout'         => 6,
            'user_agent'      => 'certy favicon proxy',
            'follow_location' => 1,
            'ignore_errors'   => true, // keep the body even on Google's 404 fallback icon
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        return ($body === false || $body === '') ? null : $body;
    }

    /** True if the bytes start with a known image signature (PNG/GIF/ICO/JPEG). */
    private function looksLikeImage(string $bytes): bool
    {
        $sigs = ["\x89PNG", "GIF8", "\x00\x00\x01\x00", "\xFF\xD8\xFF"];
        foreach ($sigs as $sig) {
            if (str_starts_with($bytes, $sig)) {
                return true;
            }
        }
        return false;
    }

    /** Where a host's icon is cached on disk. */
    private function cachePath(string $host, int $size): string
    {
        return BASE_PATH . '/storage/cache/favicons/' . sha1($host . '|' . $size) . '.png';
    }

    /**
     * No icon available → 404 so the <img onerror> hides itself cleanly.
     * Returns '' only to satisfy the router signature; exit() ends the request.
     */
    private function miss(): string
    {
        http_response_code(404);
        exit;
    }

    /** Strip scheme/path/www and lower-case (mirrors TargetController::cleanHost). */
    private function cleanHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }
        if (str_contains($host, '://')) {
            $host = (string) parse_url($host, PHP_URL_HOST);
        }
        $host = preg_replace('#[/:].*$#', '', (string) $host);
        $host = preg_replace('#^www\.#', '', (string) $host);
        // Only sane host characters survive — defence in depth for the cache key.
        return preg_match('/^[a-z0-9.-]{1,253}$/', (string) $host) ? (string) $host : '';
    }
}
