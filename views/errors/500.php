<?php /** Standalone 500 page — rendered with NO layout (no DB/session needed). */ ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Something went wrong</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Schibsted+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
    <div class="auth-wrap"><div class="text-center">
        <h1 style="font-size:3rem;">Something went wrong</h1>
        <p class="text-muted-2">An unexpected error occurred. Please try again in a moment.</p>
        <a class="btn btn-primary" href="<?= e(url('/')) ?>">Go home</a>
    </div></div>
</body>
</html>
