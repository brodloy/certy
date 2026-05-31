<?php
/**
 * CONFIG — copy this file to config.php and edit it for your machine.
 * config.php is gitignored. Read any value with config('key').
 */

return [
    // App
    'app_name' => 'certy.io',
    'app_url'  => 'http://localhost:8000',
    'debug'    => true,        // true locally (show errors). Set FALSE in production.
    'timezone' => 'UTC',       // how dates are DISPLAYED (stored in UTC always). e.g. 'Europe/London'

    // Database — defaults match MAMP. WAMP/standard MySQL uses port 3306.
    'db_host' => '127.0.0.1',
    'db_port' => '8889',
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
