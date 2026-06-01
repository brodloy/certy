<?php
/**
 * PUBLIC LAYOUT — the shell for marketing / signed-out pages.
 * The page's HTML arrives as $content; we wrap it with nav + footer.
 *
 * @var string $title
 * @var string $content
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php /* Keep the site out of search engines until launch (search_indexable => true). */ ?>
    <?php if (!config('search_indexable', false)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
    <title><?= e($title ?? config('app_name')) ?></title>
    <?php /* Social preview when the link is shared (LinkedIn / CV / etc.). */ ?>
    <meta name="description" content="certy watches your SSL certificates and domains and warns you before they expire — a from-scratch, no-dependency PHP 8 app.">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e(config('app_name')) ?>">
    <meta property="og:title" content="certy — SSL &amp; domain expiry monitor">
    <meta property="og:description" content="Get warned before a certificate or domain lapses. Built from scratch in PHP 8 — no framework, no dependencies.">
    <meta property="og:url" content="<?= e(url('/')) ?>">
    <meta name="twitter:card" content="summary">
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/favicon.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(url('assets/img/favicon-32.png')) ?>">
    <link rel="icon" href="<?= e(url('assets/img/favicon.ico')) ?>" sizes="any">
    <link rel="apple-touch-icon" href="<?= e(url('assets/img/favicon-180.png')) ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
    <?php include BASE_PATH . '/views/partials/public-nav.php'; ?>

    <main>
        <div class="container" style="max-width: 960px;">
            <?php include BASE_PATH . '/views/partials/flash.php'; ?>
        </div>
        <?= $content ?>
    </main>

    <footer class="site-footer">
        <div class="container text-center" style="max-width: 960px;">
            &copy; <?= date('Y') ?> <?= e(config('app_name')) ?>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
