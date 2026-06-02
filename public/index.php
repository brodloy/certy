<?php
/**
 * FRONT CONTROLLER — every request enters here (the .htaccess rewrites all URLs
 * to this file). This is your dispatcher's front door.
 *
 * It does three things: start the app, send security headers, run the router.
 */

require dirname(__DIR__) . '/bootstrap.php';

// Security headers on every response (clickjacking, MIME sniffing, a basic CSP).
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
// Force HTTPS for a year once seen over TLS (ignored by browsers over plain HTTP).
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header(
    "Content-Security-Policy: default-src 'self'; "
    . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
    . "font-src 'self' https://fonts.gstatic.com; "
    . "script-src 'self' https://cdn.jsdelivr.net; "
    . "img-src 'self' data:"
);

// Load the route table and dispatch the current request.
$router = new Router();
require BASE_PATH . '/routes.php';
$router->dispatch();
