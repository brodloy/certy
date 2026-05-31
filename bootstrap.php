<?php
/**
 * BOOTSTRAP — everything the app needs before it can handle a request (or a CLI
 * command). Read it top to bottom and you know the app's state by the end:
 *   1. constants + config   2. autoloader   3. helpers
 *   4. error handling       5. timezone + session   6. "remember me" login
 *
 * Both public/index.php (web) and ./console (CLI) require this file.
 */

define('BASE_PATH', __DIR__);
define('IS_CLI', PHP_SAPI === 'cli');

// 1. Config ----------------------------------------------------------------
$configFile = is_file(BASE_PATH . '/config.php')
    ? BASE_PATH . '/config.php'
    : BASE_PATH . '/config.example.php';
$GLOBALS['config'] = require $configFile;

// 2. Autoloader ------------------------------------------------------------
// `new Foo()` makes PHP ask this to find Foo's file under app/.
// No Composer, no namespaces — drop a class file in any listed folder and use it.
spl_autoload_register(function (string $class): void {
    foreach (['/app/', '/app/Controllers/', '/app/Checks/', '/app/Services/'] as $dir) {
        $path = BASE_PATH . $dir . $class . '.php';
        if (is_file($path)) {
            require $path;
            return;
        }
    }
});

// 3. Global helpers --------------------------------------------------------
require BASE_PATH . '/app/helpers.php';

// 4. Error handling --------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0'); // we render errors ourselves

set_error_handler(function ($severity, $message, $file, $line) {
    // Honour the @ silencer and the active error_reporting mask: a suppressed
    // warning (e.g. @stream_socket_client on an unreachable host) must NOT throw,
    // so checkers can return their graceful fail() result instead of aborting.
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e): void {
    log_message('error', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    if (IS_CLI) {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    if (config('debug')) {
        echo '<h1>Error</h1><pre>' . e((string) $e) . '</pre>';
        return;
    }
    // Clean, styled 500 in production. Self-contained (no layout/DB) and wrapped
    // so a failure while rendering the error can't loop — falls back to text.
    try {
        echo view('errors/500', ['title' => 'Something went wrong'], null);
    } catch (Throwable) {
        echo '<h1>Something went wrong</h1><p>Please try again later.</p>';
    }
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        log_message('error', 'FATAL: ' . $err['message'] . ' @ ' . $err['file'] . ':' . $err['line']);
    }
});

// 5. Timezone + session ----------------------------------------------------
date_default_timezone_set('UTC'); // store everything in UTC; format for display only

if (!IS_CLI) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $https,
    ]);
    session_start();

    // 6. "Remember me": if there's a valid remember cookie but no session, log in.
    auth()->attemptRememberLogin();
}
