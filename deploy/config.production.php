<?php
/**
 * PRODUCTION CONFIG TEMPLATE for the VPS.
 *
 * On the server, copy this to the project root as config.php and fill in the
 * real values:   cp deploy/config.production.php config.php
 * config.php is gitignored, so your secrets are never committed and a deploy
 * (git reset --hard) never overwrites them. See docs/deployment.md.
 */

return [
    // App
    'app_name' => 'certy',
    'app_url'  => 'https://certy.bradleyboothman.dev', // your real subdomain, https
    'debug'    => false,                               // MUST be false in production
    'timezone' => 'Europe/London',                      // DISPLAY tz; storage stays UTC
    'search_indexable' => false,  // false = noindex (private testing). Set true at public launch.

    // Monitoring
    'scan_interval_minutes' => 720,  // 12h; the systemd timer fires hourly but --due throttles
    'alerts_enabled'        => true,

    // Database — the dedicated MySQL user you create on the box (never root)
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'certy',
    'db_user' => 'certy',
    'db_pass' => 'CHANGE-ME-to-a-strong-password',

    // Email
    //   Start as 'log' — verify/reset links land in storage/logs/mail.log, so you
    //   can finish setup before email delivery exists. Switch to 'smtp' once the
    //   SMTP sender + relay are wired up (docs/deployment.md §Email / Phase 6),
    //   then fill the smtp_* keys below.
    'mail_driver' => 'log',
    'mail_from'   => 'no-reply@bradleyboothman.dev',
    // 'smtp_host'   => 'smtp.resend.com',
    // 'smtp_port'   => 587,
    // 'smtp_user'   => 'resend',
    // 'smtp_pass'   => '',            // the relay's API key / SMTP password
    // 'smtp_secure' => 'tls',         // 'tls' (STARTTLS on 587) or 'ssl' (465)

    // Google sign-in (optional). Callback: https://certy.bradleyboothman.dev/auth/google/callback
    'google_enabled'       => false,
    'google_client_id'     => '',
    'google_client_secret' => '',

    // GitHub sign-in (optional). Callback: https://certy.bradleyboothman.dev/auth/github/callback
    'github_enabled'       => false,
    'github_client_id'     => '',
    'github_client_secret' => '',
];
