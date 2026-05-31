<?php
/**
 * APP LAYOUT — the shell for the signed-in area (sidebar + top bar).
 * @var string $title
 * @var string $content
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php /* Apply the saved theme (or OS preference) BEFORE first paint — no flash. */ ?>
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('certy-theme');
                var dark = saved ? saved === 'dark'
                    : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
            } catch (e) {}
        })();
    </script>
    <title><?= e($title ?? 'Dashboard') ?> · <?= e(config('app_name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/favicon.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(url('assets/img/favicon-32.png')) ?>">
    <link rel="icon" href="<?= e(url('assets/img/favicon.ico')) ?>" sizes="any">
    <link rel="apple-touch-icon" href="<?= e(url('assets/img/favicon-180.png')) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="check-url" content="<?= e(url('/targets/check')) ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
    <div class="app-shell" id="appShell">
        <?php include BASE_PATH . '/views/partials/app-sidebar.php'; ?>
        <div class="app-nav-backdrop" data-nav-close></div>

        <div class="app-main">
            <header class="app-topbar">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="app-nav-toggle" data-nav-open
                            aria-label="Open menu" aria-controls="appShell" aria-expanded="false">&#9776;</button>
                    <div class="fw-medium"><?= e($title ?? 'Dashboard') ?></div>
                </div>
                <div class="text-muted-2" style="font-size:.9rem;">
                    <?= e(current_user()['Name'] ?? '') ?>
                </div>
            </header>

            <div class="app-content">
                <?php include BASE_PATH . '/views/partials/verify-banner.php'; ?>
                <?php include BASE_PATH . '/views/partials/flash.php'; ?>
                <?= $content ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
