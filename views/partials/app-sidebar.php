<?php
/** App sidebar. The active() helper highlights the current section. */
$path = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
$active = fn (string $prefix): string => str_starts_with($path, $prefix) ? ' active' : '';
// Admins land on the admin overview; regular users on their dashboard.
$home = is_admin() ? '/admin' : '/dashboard';
?>
<aside class="app-sidebar">
    <a href="<?= e(url($home)) ?>" class="brand text-decoration-none"><svg class="brand-mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg><?= e(config('app_name')) ?></a>
    <?php
    // Monochrome inline icons (stroke = currentColor) so the whole nav stays
    // consistent and inherits the link colour, including the active state.
    $icon = fn (string $body): string =>
        '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $body . '</svg>';
    ?>
    <nav>
        <?php if (is_admin()): ?>
            <a class="nav-item-link<?= $path === '/admin' ? ' active' : '' ?>" href="<?= e(url('/admin')) ?>"><?= $icon('<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>') ?>Admin</a>
            <a class="nav-item-link<?= str_starts_with($path, '/admin/users') ? ' active' : '' ?>" href="<?= e(url('/admin/users')) ?>"><?= $icon('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>') ?>Users</a>
        <?php else: ?>
            <a class="nav-item-link<?= $active('/dashboard') ?>" href="<?= e(url('/dashboard')) ?>"><?= $icon('<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>') ?>Dashboard</a>
        <?php endif; ?>
        <a class="nav-item-link<?= $active('/settings') ?>" href="<?= e(url('/settings')) ?>"><?= $icon('<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>') ?>Settings</a>
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
