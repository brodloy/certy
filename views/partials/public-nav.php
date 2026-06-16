<?php /** Public top nav. Links depend on whether someone is logged in. */ ?>
<nav class="site-nav">
    <div class="container d-flex align-items-center justify-content-between py-3" style="max-width: 960px;">
        <a class="brand" href="<?= e(url('/')) ?>"><svg class="brand-mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg><?= e(config('app_name')) ?></a>
        <div class="d-flex align-items-center gap-3">
            <a class="nav-link d-inline-flex align-items-center gap-1" href="https://github.com/brodloy/certy" target="_blank" rel="noopener" title="View source on GitHub">
                <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8z"/></svg>
                Source
            </a>
            <?php if (current_user() !== null): ?>
                <a class="nav-link d-inline" href="<?= e(url('/dashboard')) ?>">Dashboard</a>
            <?php else: ?>
                <a class="nav-link d-inline" href="<?= e(url('/login')) ?>">Sign in</a>
                <a class="btn btn-primary btn-sm" href="<?= e(url('/register')) ?>">Get started</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
