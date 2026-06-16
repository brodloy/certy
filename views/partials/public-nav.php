<?php /** Public top nav. Links depend on whether someone is logged in. */ ?>
<nav class="site-nav">
    <div class="container d-flex align-items-center justify-content-between py-3" style="max-width: 960px;">
        <a class="brand" href="<?= e(url('/')) ?>"><svg class="brand-mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg><?= e(config('app_name')) ?></a>
        <div class="d-flex align-items-center gap-3">
            <?php if (current_user() !== null): ?>
                <a class="nav-link d-inline" href="<?= e(url('/dashboard')) ?>">Dashboard</a>
            <?php else: ?>
                <a class="nav-link d-inline" href="<?= e(url('/login')) ?>">Sign in</a>
                <a class="btn btn-primary btn-sm" href="<?= e(url('/register')) ?>">Get started</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
