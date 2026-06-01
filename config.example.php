<?php
/**
 * CONFIG — copy this file to config.php and edit it for your machine.
 * config.php is gitignored. Read any value with config('key').
 */

return [
    // App
    'app_name' => 'certy',
    'app_url'  => 'http://localhost:8000',
    'debug'    => true,        // true locally (show errors). Set FALSE in production.
    'timezone' => 'UTC',       // how dates are DISPLAYED (stored in UTC always). e.g. 'Europe/London'
    'search_indexable' => false, // false = noindex (pre-launch/testing). Set true at public launch.
    'signup_code'      => '',    // if set, the register form requires this code (private-beta gate). '' = open.
    'demo_enabled'     => true,  // show the "Try the live demo" button + enable /demo one-click login
    'demo_email'       => 'demo@example.com', // identifies the shared demo account (reset by `console demo:reset`)

    // Monitoring
    'scan_interval_minutes' => 720, // `monitor:run --due` skips targets checked within this window (minutes; 720 = 12h)
    'alerts_enabled'        => true, // email alerts (expiry tiers + failures) from monitor:run; false disables all alerting
    'scan_retries'          => 2,    // retry a FAILED scheduled check this many extra times before accepting it (flap handling)
    'scan_retry_delay_ms'   => 1500, // pause between those retries

    // Database — 127.0.0.1:3306, root/root. (Classic MAMP defaults to port 8889.)
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'certy',
    'db_user' => 'root',
    'db_pass' => 'root',

    // Email — 'log' writes to storage/logs/mail.log (no setup). 'mail' uses PHP mail().
    'mail_driver' => 'log',
    'mail_from'   => 'no-reply@example.com',

    // Uploads

    // Google sign-in (optional). When false, the button is hidden and the
    // /auth/google routes 404. Fill these in and flip to true to enable.
    'google_enabled'       => false,
    'google_client_id'     => '',
    'google_client_secret' => '',

    // GitHub sign-in (optional). Same as Google: fill these in and flip to true.
    // Callback URL on GitHub: {app_url}/auth/github/callback
    'github_enabled'       => false,
    'github_client_id'     => '',
    'github_client_secret' => '',
];
