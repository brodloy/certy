<?php
/**
 * ADMIN DASHBOARD — system-wide operational overview (read-only).
 * @var array $users @var int $newUsers7 @var array $targets @var array $byType
 * @var array $health @var array $checks @var array $runStats
 * @var ?array $lastDue @var ?array $lastFull @var array $recentRuns
 */
$num = fn ($v): string => number_format((int) $v);

// Renders one "last run" panel (scheduled or manual). $run may be null.
$runPanel = function (?array $run, string $heading, string $empty): string {
    ob_start(); ?>
    <div class="card h-100"><div class="card-body">
        <div class="stat-label"><?= e($heading) ?></div>
        <?php if ($run === null): ?>
            <div class="text-faint mt-2"><?= e($empty) ?></div>
        <?php else: ?>
            <div class="fw-medium mt-1" style="font-size:1.05rem;"><?= e(format_date($run['StartedAt'])) ?></div>
            <div class="d-flex flex-wrap gap-3 mt-2" style="font-size:.85rem;">
                <?php if ($run['Mode'] === 'due'): ?>
                    <span class="text-muted-2"><?= (int) $run['DueCount'] ?> due</span>
                <?php endif; ?>
                <span class="text-muted-2"><?= (int) $run['CheckedCount'] ?> checked</span>
                <span style="color:var(--ok)"><?= (int) $run['OkCount'] ?> ok</span>
                <span style="color:var(--danger)"><?= (int) $run['FailedCount'] ?> failed</span>
                <span class="text-faint"><?= (int) $run['DurationMs'] ?> ms</span>
            </div>
        <?php endif; ?>
    </div></div>
    <?php return (string) ob_get_clean();
};
?>
<div class="mb-4">
    <h1 class="mb-1">Admin</h1>
    <p class="text-muted-2 mb-0">System-wide overview — users, targets, and scanner activity.</p>
</div>

<!-- Top-line KPIs -->
<div class="row g-3 mb-4">
    <div class="col-sm-3 col-6"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Users</div><div class="stat-value"><?= $num($users['total'] ?? 0) ?></div>
        <div class="text-faint" style="font-size:.8rem;"><?= $num($users['verified'] ?? 0) ?> verified</div>
    </div></div></div>
    <div class="col-sm-3 col-6"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Targets</div><div class="stat-value"><?= $num($targets['total'] ?? 0) ?></div>
        <div class="text-faint" style="font-size:.8rem;"><?= $num($targets['active'] ?? 0) ?> active · <?= $num($targets['paused'] ?? 0) ?> paused</div>
    </div></div></div>
    <div class="col-sm-3 col-6"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Checks recorded</div><div class="stat-value"><?= $num($checks['total'] ?? 0) ?></div>
        <div class="text-faint" style="font-size:.8rem;">last <?= $checks['last'] ? e(format_date($checks['last'], 'M j, g:i A')) : '—' ?></div>
    </div></div></div>
    <div class="col-sm-3 col-6"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Monitor runs</div><div class="stat-value"><?= $num($runStats['total'] ?? 0) ?></div>
        <div class="text-faint" style="font-size:.8rem;"><?= $num($runStats['due_runs'] ?? 0) ?> scheduled · <?= $num($runStats['full_runs'] ?? 0) ?> manual</div>
    </div></div></div>
</div>

<!-- Scanner activity -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="h5 mb-0">Scanner</h3>
    <?php if ($recentRuns !== []): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/admin/export')) ?>">Export runs CSV</a>
    <?php endif; ?>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-6"><?= $runPanel($lastDue, 'Last scheduled run (--due)', 'No scheduled runs recorded yet.') ?></div>
    <div class="col-md-6"><?= $runPanel($lastFull, 'Last manual run (full)', 'No manual runs recorded yet.') ?></div>
</div>

<?php if ($recentRuns !== []): ?>
    <div class="table-card mb-4">
        <table class="table">
            <thead><tr>
                <th>When</th><th>Mode</th><th>Due</th><th>Checked</th><th>Ok</th><th>Failed</th><th>Duration</th>
            </tr></thead>
            <tbody>
            <?php foreach ($recentRuns as $run): ?>
                <tr>
                    <td style="font-family:var(--font-mono);font-size:.82rem;white-space:nowrap;"><?= e(format_date($run['StartedAt'])) ?></td>
                    <td><span class="badge-soft<?= $run['Mode'] === 'full' ? ' is-active' : '' ?>"><?= $run['Mode'] === 'due' ? 'scheduled' : 'manual' ?></span></td>
                    <td><?= $run['DueCount'] === null ? '<span class="text-faint">—</span>' : (int) $run['DueCount'] ?></td>
                    <td><?= (int) $run['CheckedCount'] ?></td>
                    <td style="color:var(--ok)"><?= (int) $run['OkCount'] ?></td>
                    <td style="color:<?= (int) $run['FailedCount'] > 0 ? 'var(--danger)' : 'var(--text-faint)' ?>"><?= (int) $run['FailedCount'] ?></td>
                    <td class="text-faint" style="font-size:.82rem;"><?= (int) $run['DurationMs'] ?> ms</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Targets + Users breakdown -->
<div class="row g-3">
    <div class="col-md-6"><div class="card h-100"><div class="card-body">
        <h3 class="h6 mb-3">Targets</h3>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <?php foreach (['healthy' => 'ok', 'warning' => 'warn', 'critical' => 'danger', 'expired' => 'danger', 'failed' => 'danger', 'unknown' => 'neutral'] as $k => $var): ?>
                <span class="badge-soft is-<?= e($k) ?>"><?= e((string) $health[$k]) ?> <?= e($k) ?></span>
            <?php endforeach; ?>
        </div>
        <div class="text-muted-2" style="font-size:.9rem;">
            <?php foreach ($byType as $t): ?>
                <div><?= e($t['label']) ?>: <strong><?= $num($t['c']) ?></strong></div>
            <?php endforeach; ?>
            <?php if ($byType === []): ?><div class="text-faint">No targets yet.</div><?php endif; ?>
        </div>
    </div></div></div>
    <div class="col-md-6"><div class="card h-100"><div class="card-body d-flex flex-column">
        <h3 class="h6 mb-3">Users</h3>
        <div class="text-muted-2" style="font-size:.9rem;">
            <div><strong><?= $num($users['total'] ?? 0) ?></strong> total · <strong><?= $num($users['verified'] ?? 0) ?></strong> verified · <strong><?= $num($users['admins'] ?? 0) ?></strong> admin</div>
            <div class="mt-1"><strong><?= $num($newUsers7) ?></strong> signed up in the last 7 days</div>
        </div>
        <a class="btn btn-outline-secondary btn-sm mt-3 align-self-start" href="<?= e(url('/admin/users')) ?>">Manage users</a>
    </div></div></div>
</div>
