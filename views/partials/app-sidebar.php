<?php
/** App sidebar. The active() helper highlights the current section. */
$path = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
$active = fn (string $prefix): string => str_starts_with($path, $prefix) ? ' active' : '';
?>
<aside class="app-sidebar">
    <a href="<?= e(url('/dashboard')) ?>" class="brand text-decoration-none"><svg class="brand-mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg><?= e(config('app_name')) ?></a>
    <nav>
        <a class="nav-item-link<?= $active('/dashboard') ?>" href="<?= e(url('/dashboard')) ?>">&#9636; Dashboard</a>
        <a class="nav-item-link<?= $active('/targets') ?>" href="<?= e(url('/targets')) ?>">&#9670; Targets</a>
        <a class="nav-item-link<?= $active('/scans') ?>" href="<?= e(url('/scans')) ?>">&#9203; Scans</a>
        <a class="nav-item-link<?= $active('/settings') ?>" href="<?= e(url('/settings')) ?>">&#9881; Settings</a>
        <?php if (is_admin()): ?>
            <a class="nav-item-link<?= $active('/admin') ?>" href="<?= e(url('/admin/users')) ?>">&#9889; Admin</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-foot">
        <div class="px-2 mb-2" style="font-size:.82rem;">
            <div class="fw-medium text-truncate"><?= e(current_user()['Name'] ?? '') ?></div>
            <div class="text-faint text-truncate"><?= e(current_user()['Email'] ?? '') ?></div>
        </div>
        <form method="post" action="<?= e(url('/logout')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Sign out</button>
        </form>
    </div>
</aside>
