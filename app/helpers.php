<?php
/**
 * HELPERS — small global functions available everywhere (the autoloader loads
 * this once at boot). This is your "everything accessible globally" file.
 *
 * Grouped by job: output, config, urls, views, redirects, input, validation,
 * flash messages, CSRF, auth shortcuts, logging.
 */

// ---- Shared service accessors --------------------------------------------
// These live here (always loaded) rather than in the class files, because the
// autoloader only loads CLASSES on demand — not these functions. Calling them
// does `new Database()` / `new Auth()`, which DOES autoload the class. Each
// returns one shared instance for the whole request.

function db(): Database
{
    static $instance = null;
    return $instance ??= new Database();
}

function auth(): Auth
{
    static $instance = null;
    return $instance ??= new Auth();
}

// ---- Output / escaping ----------------------------------------------------

/**
 * Escape a value for safe output in HTML. Use this EVERY time you print
 * something dynamic in a view: <?= e($user['Name']) ?>. This is your defence
 * against XSS — when in doubt, wrap it in e().
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// ---- Config ---------------------------------------------------------------

/** Read a setting from config.php, e.g. config('app_url'). */
function config(string $key, mixed $default = null): mixed
{
    return $GLOBALS['config'][$key] ?? $default;
}

// ---- URLs -----------------------------------------------------------------

/** Build an absolute URL from a path: url('/login') → http://localhost:8000/login */
function url(string $path = ''): string
{
    return rtrim(config('app_url'), '/') . '/' . ltrim($path, '/');
}

// ---- Views ----------------------------------------------------------------

/**
 * Render a view file and return the HTML. Variables in $data become local
 * variables inside the template ($data['title'] → $title).
 *
 * By default the view is wrapped in views/layout/public.php. Pass a different
 * layout ('app') for the signed-in area, or null for no layout (fragments).
 */
function view(string $template, array $data = [], ?string $layout = 'public'): string
{
    $renderFile = function (string $__file, array $__data): string {
        extract($__data, EXTR_SKIP);
        ob_start();
        require BASE_PATH . '/views/' . $__file . '.php';
        return (string) ob_get_clean();
    };

    $content = $renderFile($template, $data);

    if ($layout === null) {
        return $content;
    }

    // The layout prints $content somewhere in its HTML shell.
    return $renderFile('layout/' . $layout, array_merge($data, ['content' => $content]));
}

// ---- Redirects ------------------------------------------------------------

/** Send the browser to another path and stop. */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/** Redirect AND leave a one-time flash message (shown once on the next page). */
function redirect_with(string $path, string $type, string $message): never
{
    flash($type, $message);
    redirect($path);
}

/**
 * Failed-validation redirect: remember the per-field errors AND the submitted
 * values (so the form re-fills), then send the user back to it. Optional
 * $message also shows as a flash banner at the top.
 */
function redirect_errors(string $path, array $errors, array $old, string $message = ''): never
{
    remember_errors($errors);
    remember_old($old);
    if ($message !== '') {
        flash('error', $message);
    }
    redirect($path);
}

/** Send a JSON response and stop. Use for API endpoints. */
function json(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Stream a CSV file download and stop. $columns is the header row; $rows is a
 * list of flat arrays in the same column order. No dependency — fputcsv handles
 * quoting/escaping.
 */
function csv_download(string $filename, array $columns, iterable $rows): never
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $columns);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

/** Stop with an HTTP error and a small page (or JSON for /api paths). */
function abort(int $status, string $message = ''): never
{
    http_response_code($status);
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    if (str_starts_with($path, '/api/')) {
        echo json_encode(['error' => $message ?: 'Error ' . $status]);
    } elseif ($status === 404) {
        echo view('errors/404', ['title' => 'Not found']);
    } else {
        echo '<h1>' . $status . '</h1><p>' . e($message) . '</p>';
    }
    exit;
}

// ---- Dates ----------------------------------------------------------------

/**
 * Format a stored UTC datetime for display, converted to the configured
 * timezone. e.g. format_date($row['CreatedAt']) → "Jan 5, 2026, 2:30 PM".
 */
function format_date(?string $utc, string $format = 'M j, Y, g:i A'): string
{
    if (empty($utc)) {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        return $dt->setTimezone(new DateTimeZone(config('timezone', 'UTC')))->format($format);
    } catch (Throwable) {
        return $utc;
    }
}

// ---- Request input --------------------------------------------------------

/** True if this request is a form submission (POST). */
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Read a submitted field (POST first, then query string), trimmed. */
function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** The visitor's IP address (best effort). */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ---- Flash messages + old input (survive one redirect) --------------------

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][$type] = $message;
}

/** Pull all flash messages and clear them. Returns ['success' => '...', ...]. */
function flash_all(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

/** Remember submitted values so a form can be re-filled after a failed submit. */
function remember_old(array $values): void
{
    $_SESSION['_old'] = $values;
}

/** Get a remembered value back into a form field. Cleared after the page renders. */
function old(string $key, string $default = ''): string
{
    return (string) ($_SESSION['_old'][$key] ?? $default);
}

/** Called by the layout once the page is built, to drop one-shot old input. */
function clear_old(): void
{
    unset($_SESSION['_old'], $_SESSION['_errors']);
}

// ---- Per-field validation errors (survive one redirect) -------------------
// Pattern: in a controller, build an array of [field => message], and if it's
// non-empty, remember_errors() + remember_old() + redirect back to the form.
// In the view, field_error('email') prints the message under that input.

/** Stash field errors to show after a redirect back to the form. */
function remember_errors(array $errors): void
{
    $_SESSION['_errors'] = $errors;
}

/** Get the error message for one field (or '' if none). */
function error(string $field): string
{
    return (string) ($_SESSION['_errors'][$field] ?? '');
}

/** True if a field has an error — handy for adding the is-invalid class. */
function has_error(string $field): bool
{
    return isset($_SESSION['_errors'][$field]);
}

/** ' is-invalid' if the field errored (append to a form-control's class). */
function invalid_class(string $field): string
{
    return has_error($field) ? ' is-invalid' : '';
}

/** Render the small red message under a field, if it has one. */
function field_error(string $field): string
{
    $msg = error($field);
    return $msg === '' ? '' : '<div class="invalid-feedback d-block">' . e($msg) . '</div>';
}

// ---- CSRF (cross-site request forgery) protection -------------------------
// Every form must include csrf_field(). The router checks it on every POST,
// so a forged request from another site is rejected before your code runs.

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** Drop this inside every <form>: <?= csrf_field() ?> */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Throws if the submitted token is missing or wrong. Called by the router. */
function csrf_verify(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired. Please go back and try again.');
    }
}

// ---- Auth shortcuts -------------------------------------------------------

/** The currently logged-in user as an array, or null. */
function current_user(): ?array
{
    return auth()->user();
}

/** Call at the top of any page that requires login. Redirects guests to /login. */
function require_login(): void
{
    if (!auth()->check()) {
        redirect('/login');
    }
}

/** True if the logged-in user is an admin. */
function is_admin(): bool
{
    return (current_user()['Role'] ?? '') === 'admin';
}

/** Require an admin; guests go to login, non-admins get a 403. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        abort(403, 'Admins only.');
    }
}

/** True if the logged-in user has verified their email. */
function is_verified(): bool
{
    return !empty(current_user()['VerifiedAt']);
}

// ---- Pagination -----------------------------------------------------------

/**
 * Render simple "‹ 1 2 3 ›" pagination links. $meta is what db()->paginate()
 * returns. $baseUrl is the path without ?page (e.g. '/admin/users').
 */
function pagination_links(array $meta, string $baseUrl, array $query = []): string
{
    if (($meta['totalPages'] ?? 1) <= 1) {
        return '';
    }
    // Extra query params (e.g. active filters) appended to every page link.
    $extra = '';
    foreach ($query as $k => $v) {
        if ($v !== '' && $v !== null) {
            $extra .= '&' . rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
        }
    }
    $link = function (int $p, string $label, bool $on = false) use ($baseUrl, $extra): string {
        $cls = 'btn btn-sm ' . ($on ? 'btn-primary' : 'btn-outline-secondary');
        return '<a class="' . $cls . '" href="' . e(url($baseUrl . '?page=' . $p . $extra)) . '">' . $label . '</a>';
    };
    $out = '<nav class="d-flex gap-2 mt-3">';
    if ($meta['page'] > 1) {
        $out .= $link($meta['page'] - 1, '&lsaquo; Prev');
    }
    for ($p = 1; $p <= $meta['totalPages']; $p++) {
        $out .= $link($p, (string) $p, $p === $meta['page']);
    }
    if ($meta['page'] < $meta['totalPages']) {
        $out .= $link($meta['page'] + 1, 'Next &rsaquo;');
    }
    return $out . '</nav>';
}

// ---- Logging --------------------------------------------------------------

/** Append a line to storage/logs/app.log. Never throws (logging must not break the app). */
function log_message(string $level, string $message): void
{
    try {
        $line = '[' . gmdate('Y-m-d H:i:s') . '] ' . strtoupper($level) . ': ' . $message . PHP_EOL;
        file_put_contents(BASE_PATH . '/storage/logs/app.log', $line, FILE_APPEND | LOCK_EX);
    } catch (Throwable) {
        // ignore
    }
}

// ---- Email (no dependency) ------------------------------------------------

/**
 * Send an email. Pass $html for a rich message — it's sent as multipart/
 * alternative (HTML + the plain-text fallback in $text); omit it for a plain
 * text-only mail (the existing verification/reset callers).
 *
 * Locally (mail_driver = 'log') it writes the text part to storage/logs/mail.log
 * so you can copy links out without any SMTP setup. In production
 * (mail_driver = 'mail') it uses PHP's built-in mail().
 */
function send_mail(string $to, string $subject, string $text, ?string $html = null): void
{
    if (config('mail_driver') === 'log') {
        // Also drop the HTML body to a file you can open in a browser, so the
        // local 'log' driver lets you preview the real rendered email.
        $htmlNote = '';
        if ($html !== null) {
            $dir = BASE_PATH . '/storage/logs/emails';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $file = $dir . '/' . gmdate('Ymd-His') . '-' . substr(md5($to . $subject . microtime()), 0, 6) . '.html';
            @file_put_contents($file, $html);
            $htmlNote = 'HTML: ' . str_replace(BASE_PATH, '', $file) . PHP_EOL;
        }
        $entry = str_repeat('=', 60) . PHP_EOL
            . 'To: ' . $to . PHP_EOL
            . 'Subject: ' . $subject . PHP_EOL
            . $htmlNote . PHP_EOL
            . $text . PHP_EOL;
        file_put_contents(BASE_PATH . '/storage/logs/mail.log', $entry, FILE_APPEND | LOCK_EX);
        return;
    }

    // 'smtp' — send through an authenticated SMTP relay (Resend/Postmark/etc.).
    // Best-effort: a relay hiccup must never break a signup/reset, so failures
    // are logged (storage/logs/app.log), not thrown.
    if (config('mail_driver') === 'smtp') {
        try {
            smtp_send($to, $subject, $text, $html);
        } catch (Throwable $e) {
            log_message('error', 'SMTP send failed: ' . $e->getMessage());
        }
        return;
    }

    $from = 'From: ' . config('mail_from') . "\r\n" . "MIME-Version: 1.0\r\n";

    if ($html === null) {
        mail($to, $subject, $text, $from . "Content-Type: text/plain; charset=UTF-8\r\n");
        return;
    }

    // multipart/alternative — clients pick HTML, fall back to text.
    $boundary = '=_certy_' . bin2hex(random_bytes(12));
    $headers  = $from . 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n";
    $eol  = "\r\n";
    $body = '--' . $boundary . $eol
        . 'Content-Type: text/plain; charset=UTF-8' . $eol . $eol . $text . $eol . $eol
        . '--' . $boundary . $eol
        . 'Content-Type: text/html; charset=UTF-8' . $eol . $eol . $html . $eol . $eol
        . '--' . $boundary . '--' . $eol;
    mail($to, $subject, $body, $headers);
}

/**
 * Send one message through an authenticated SMTP relay, no library — just a
 * socket. Reads config: smtp_host, smtp_port, smtp_user, smtp_pass, and
 * smtp_secure ('tls' = STARTTLS on 587, 'ssl' = implicit TLS on 465). Throws a
 * RuntimeException on any protocol error (the caller in send_mail catches it).
 */
function smtp_send(string $to, string $subject, string $text, ?string $html): void
{
    $host   = (string) config('smtp_host');
    $port   = (int) config('smtp_port', 587);
    $user   = (string) config('smtp_user');
    $pass   = (string) config('smtp_pass');
    $secure = config('smtp_secure', 'tls');
    $ehloHost = parse_url((string) config('app_url'), PHP_URL_HOST) ?: 'localhost';

    if ($host === '') {
        throw new RuntimeException('smtp_host is not configured');
    }

    $transport = $secure === 'ssl' ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
    $fp = @stream_socket_client($transport, $errno, $errstr, 15);
    if ($fp === false) {
        throw new RuntimeException("connect failed: {$errstr} ({$errno})");
    }
    stream_set_timeout($fp, 15);

    // Read one (possibly multi-line) SMTP reply; the last line has a SPACE after
    // the 3-digit code, continuation lines have a '-'.
    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $data .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    // Send a command and require the reply to start with $expect (e.g. '250').
    $cmd = function (string $line, string $expect) use ($fp, $read): void {
        fwrite($fp, $line . "\r\n");
        $resp = $read();
        if (strncmp($resp, $expect, strlen($expect)) !== 0) {
            throw new RuntimeException("expected {$expect} after '" . explode("\r\n", $line)[0] . "', got: " . trim($resp));
        }
    };

    if (strncmp($read(), '220', 3) !== 0) {       // server greeting
        throw new RuntimeException('no 220 greeting');
    }
    $cmd('EHLO ' . $ehloHost, '250');

    if ($secure === 'tls') {
        $cmd('STARTTLS', '220');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('STARTTLS handshake failed');
        }
        $cmd('EHLO ' . $ehloHost, '250');         // must re-EHLO after TLS
    }

    if ($user !== '') {
        $cmd('AUTH LOGIN', '334');
        $cmd(base64_encode($user), '334');
        $cmd(base64_encode($pass), '235');
    }

    $cmd('MAIL FROM:<' . config('mail_from') . '>', '250');
    $cmd('RCPT TO:<' . $to . '>', '250');
    $cmd('DATA', '354');

    // Body: dot-stuff any line that begins with '.' (SMTP transparency), then
    // terminate with <CRLF>.<CRLF>.
    $message = (string) preg_replace('/^\./m', '..', smtp_build_message($to, $subject, $text, $html));
    fwrite($fp, $message . "\r\n.\r\n");
    if (strncmp($read(), '250', 3) !== 0) {
        throw new RuntimeException('message not accepted (no 250 after DATA)');
    }

    fwrite($fp, "QUIT\r\n");
    fclose($fp);
}

/** Build the raw RFC-5322 message (headers + body) for smtp_send(). */
function smtp_build_message(string $to, string $subject, string $text, ?string $html): string
{
    $host = parse_url((string) config('app_url'), PHP_URL_HOST) ?: 'localhost';
    $eol  = "\r\n";
    // Normalise any line endings in the bodies to CRLF (SMTP requires it).
    $crlf = fn (string $s): string => (string) preg_replace('/\r\n|\r|\n/', "\r\n", $s);

    $headers = [
        'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
        'From: ' . config('mail_from'),
        'To: ' . $to,
        'Subject: ' . $subject,
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $host . '>',
        'MIME-Version: 1.0',
    ];

    if ($html === null) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        return implode($eol, $headers) . $eol . $eol . $crlf($text);
    }

    $boundary = '=_certy_' . bin2hex(random_bytes(12));
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
    $body = '--' . $boundary . $eol
        . 'Content-Type: text/plain; charset=UTF-8' . $eol . $eol . $crlf($text) . $eol . $eol
        . '--' . $boundary . $eol
        . 'Content-Type: text/html; charset=UTF-8' . $eol . $eol . $crlf($html) . $eol . $eol
        . '--' . $boundary . '--' . $eol;

    return implode($eol, $headers) . $eol . $eol . $body;
}

/**
 * A dev-only hint pointing at the mail log, for "we sent you an email" notices.
 * Empty unless the 'log' driver is active — so production (smtp/mail) shows a
 * clean message, while local dev still tells you where to read the link.
 */
function mail_log_hint(): string
{
    return config('mail_driver') === 'log' ? ' (Locally: see storage/logs/mail.log)' : '';
}

// ---- Monitoring status ----------------------------------------------------

/**
 * Derive a target's urgency from its latest check. Returns one of:
 *   'healthy' | 'warning' | 'critical' | 'expired' | 'failed' | 'unknown'
 * 'expired' = past expiry (negative days); 'critical' = 0–7 days left;
 * 'failed' = the check ran but errored (host unreachable); 'unknown' = never checked.
 * mapped to the badge / colour classes used across the dashboard. Status is
 * always DERIVED (never stored) so it can't go stale as days tick down.
 *
 *   $lastIsOk   = the MonitoredTarget.LastIsOk column (0/1/null)
 *   $daysLeft   = the MonitoredTarget.LastDaysLeft column (int/null)
 */
function monitor_status(?int $lastIsOk, ?int $daysLeft): string
{
    if ($lastIsOk === null) {
        return 'unknown';            // never checked yet
    }
    if ((int) $lastIsOk !== 1) {
        return 'failed';             // last check ran but failed (host unreachable, no TLS, etc.)
    }
    if ($daysLeft === null) {
        return 'unknown';
    }
    if ($daysLeft < 0) {
        return 'expired';            // already past expiry
    }
    if ($daysLeft <= 7) {
        return 'critical';           // 0–7 days left — urgent but still valid
    }
    if ($daysLeft <= 30) {
        return 'warning';            // within ~30 days
    }
    return 'healthy';
}

/**
 * Derive a target's status straight from a MonitoredTarget row's `Last*`
 * snapshot columns — the null-aware casting that every caller would otherwise
 * repeat. Thin wrapper over monitor_status().
 */
function target_status(array $row): string
{
    return monitor_status(
        $row['LastIsOk'] === null ? null : (int) $row['LastIsOk'],
        $row['LastDaysLeft'] === null ? null : (int) $row['LastDaysLeft'],
    );
}

/**
 * Human "days left" text for a snapshot, sign-aware:
 *   5  → "5 days left"   ·   1 → "1 day left"
 *   -3 → "expired 3 days ago"   ·   -1 → "expired 1 day ago"
 */
function days_left_label(int $days): string
{
    if ($days < 0) {
        $n = abs($days);
        return 'expired ' . $n . ' day' . ($n === 1 ? '' : 's') . ' ago';
    }
    return $days . ' day' . ($days === 1 ? '' : 's') . ' left';
}

/** Human label for a status, e.g. for a tooltip or screen-reader text. */
function monitor_status_label(string $status): string
{
    return [
        'healthy'  => 'Healthy',
        'warning'  => 'Expiring soon',
        'critical' => 'Critical',
        'expired'  => 'Expired',
        'failed'   => 'Failed',
        'unknown'  => 'Unknown',
    ][$status] ?? 'Unknown';
}

/** A coloured status pill for a monitoring status. */
function status_badge(string $status): string
{
    return '<span class="badge-soft is-' . e($status) . '">'
        . e(strtolower(monitor_status_label($status))) . '</span>';
}

// ---- Hosts ----------------------------------------------------------------

/**
 * Normalise a host the user typed: lower-case, and strip any scheme, path,
 * port or leading "www." so a pasted URL (https://www.Example.com/path) becomes
 * the bare host (example.com). Used wherever a host is stored or looked up.
 */
function clean_host(string $host): string
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
    return (string) $host;
}

// ---- Favicons -------------------------------------------------------------

/**
 * URL for a host's favicon. Points at our OWN same-origin proxy
 * (FaviconController), which fetches + caches the icon server-side. Same-origin
 * means privacy blockers (Brave/uBlock) don't block it and the user's browser
 * never talks to a third party. The source service lives in that controller.
 */
function favicon_url(string $host, int $size = 32): string
{
    return url('/favicon?host=' . rawurlencode($host) . '&sz=' . $size);
}

/**
 * A small favicon <img> for a target's host. Decorative, so alt="" and it's
 * hidden if the icon fails to load (so a broken-image glyph never shows).
 */
function favicon_img(string $host, int $size = 16): string
{
    return '<img class="favicon" src="' . e(favicon_url($host, $size * 2)) . '"'
        . ' width="' . $size . '" height="' . $size . '" alt="" loading="lazy"'
        . ' onerror="this.style.display=\'none\'">';
}
