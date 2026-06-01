<?php
/**
 * ADMIN DASHBOARD — system-wide operational overview (read-only). Health first:
 * is the scheduler firing, is anything failing, what have the scans been doing.
 * @var array $users @var int $newUsers7 @var array $targets @var array $byType
 * @var array $health @var array $checks @var array $runStats
 * @var ?array $lastDue @var ?array $lastFull @var array $recentRuns
 * @var array $scheduler @var int $failingNow @var array $queue
 * @var array $activity @var array $recentFailures
 */
$num = fn ($v): string => number_format((int) $v);
$ago = function (?int $m): string {
    if ($m === null)  return 'never';
    if ($m < 1)       return 'just now';
    if ($m < 60)      return $m . ' min ago';
    $h = intdiv($m, 60);
    if ($h < 24)      return $h . ' hr' . ($h === 1 ? '' : 's') . ' ago';
    $d = intdiv($h, 24);
    return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
};
// Pass rate from ACTUAL checks (scheduled + manual) over the last 7 days — the
// same CheckResult data the activity panel shows, so the two always agree.
$a7       = $activity['7d'];
$checked7 = (int) $a7['scheduled']['total'] + (int) $a7['manual']['total'];
$ok7      = (int) $a7['scheduled']['ok'] + (int) $a7['manual']['ok'];
$passRate = $checked7 > 0 ? (int) round($ok7 / $checked7 * 100) : null;
$queueActive = (int) ($queue['pending'] ?? 0) + (int) ($queue['running'] ?? 0);
$queueFailed = (int) ($queue['failed'] ?? 0);
$allHealthy  = $scheduler['healthy'] && $failingNow === 0 && $queueFailed === 0;

// One source's tallies for a window, as a compact "12 ok · 1 failed" line.
$srcLine = function (array $s): string {
    $ok = '<span style="color:var(--ok)">' . (int) $s['ok'] . ' ok</span>';
    $fl = (int) $s['failed'] > 0
        ? ' · <span style="color:var(--danger)">' . (int) $s['failed'] . ' failed</span>'
        : '';
    return (int) $s['total'] . ' check' . ((int) $s['total'] === 1 ? '' : 's') . ' (' . $ok . $fl . ')';
};
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="mb-1">Admin</h1>
        <p class="text-muted-2 mb-0">System health, scanner activity, users &amp; targets.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/admin/runs')) ?>">All scan runs &rarr;</a>
</div>

<!-- System status banner -->
<div class="card mb-4" style="border-left:4px solid var(--<?= $allHealthy ? 'ok' : 'danger' ?>);">
    <div class="card-body">
        <div class="stat-label">System status</div>
        <div class="fw-semibold" style="font-size:1.15rem;color:var(--<?= $allHealthy ? 'ok' : 'danger' ?>);">
            <?= $allHealthy
                ? '&#10003; Healthy — scheduler running, nothing failing'
                : '&#9888; Attention needed' ?>
        </div>
        <?php if (!$allHealthy): ?>
            <ul class="mb-0 mt-2 text-muted-2" style="font-size:.9rem;">
                <?php if (!$scheduler['healthy']): ?>
                    <li>Scheduler last ran <strong><?= e($ago($scheduler['minsAgo'])) ?></strong> — expected at least every <?= (int) $scheduler['staleAfterMin'] ?> min. Is the systemd timer up?</li>
                <?php endif; ?>
                <?php if ($failingNow > 0): ?>
                    <li><strong><?= $num($failingNow) ?></strong> target<?= $failingNow === 1 ? '' : 's' ?> currently failing their check — see "Recent failures" below.</li>
                <?php endif; ?>
                <?php if ($queueFailed > 0): ?>
                    <li><strong><?= $num($queueFailed) ?></strong> stuck/failed scan job<?= $queueFailed === 1 ? '' : 's' ?> in the queue (<code>php console db:cleanup</code> prunes old ones).</li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Health metrics -->
<div class="row g-3 mb-4">
    <div class="col-sm-3 col-6"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Scheduler</div>
        <div class="stat-value" style="font-size:1.4rem;color:var(--<?= $scheduler['healthy'] ? 'ok' : 'danger' ?>);"><?= e($ago($scheduler['minsAgo'])) ?></div>
        <div class="text-faint" style="font-size:.8rem;">last scheduled run</div>
    </div></div></div>
    <div class="col-sm-3 col-6"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Check pass rate</div>
        <div class="stat-value"><?= $passRate === null ? '—' : $passRate . '%' ?></div>
        <div class="text-faint" style="font-size:.8rem;">last 7 days · <?= $num($checked7) ?> checks</div>
    </div></div></div>
    <div class="col-sm-3 col-6"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Targets failing</div>
        <div class="stat-value" style="color:var(--<?= $failingNow > 0 ? 'danger' : 'ok' ?>);"><?= $num($failingNow) ?></div>
        <div class="text-faint" style="font-size:.8rem;">last check errored</div>
    </div></div></div>
    <div class="col-sm-3 col-6"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Queue</div>
        <div class="stat-value"><?= $num($queueActive) ?></div>
        <div class="text-faint" style="font-size:.8rem;"><?= $num($queue['pending'] ?? 0) ?> pending · <?= $num($queue['running'] ?? 0) ?> running<?= $queueFailed > 0 ? ' · <span style="color:var(--danger)">' . $num($queueFailed) . ' failed</span>' : '' ?></div>
    </div></div></div>
</div>

<!-- Scan activity by source -->
<h3 class="h5 mb-3">Scan activity</h3>
<div class="row g-3 mb-4">
    <?php foreach (['24h' => 'Last 24 hours', '7d' => 'Last 7 days'] as $win => $heading): ?>
        <div class="col-md-6"><div class="card h-100"><div class="card-body">
            <div class="stat-label mb-2"><?= e($heading) ?></div>
            <div class="d-flex flex-column gap-2" style="font-size:.9rem;">
                <div class="d-flex justify-content-between">
                    <span class="text-muted-2">Scheduled</span>
                    <span><?= $srcLine($activity[$win]['scheduled']) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted-2">User-triggered</span>
                    <span><?= $srcLine($activity[$win]['manual']) ?></span>
                </div>
            </div>
        </div></div></div>
    <?php endforeach; ?>
</div>

<!-- Top-line KPIs -->
<div class="row g-3 mb-4">
    <div class="col-sm-3 col-6"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Users</div><div class="stat-value"><?= $num($users['total'] ?? 0) ?></div>
        <div class="text-faint" style="font-size:.8rem;"><?= $num($users['verified'] ?? 0) ?> verified · <?= $num($newUsers7) ?> new (7d)</div>
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

<!-- Recent scheduled runs -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="h5 mb-0">Recent runs</h3>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/admin/runs')) ?>">View all</a>
        <?php if ($recentRuns !== []): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/admin/export')) ?>">Export CSV</a>
        <?php endif; ?>
    </div>
</div>
<?php if ($recentRuns !== []): ?>
    <div class="table-card mb-4">
        <table class="table">
            <thead><tr>
                <th>When</th><th>Mode</th><th>Due</th><th>Checked</th><th>Ok</th><th>Failed</th><th>Duration</th><th></th>
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
                    <td><a href="<?= e(url('/admin/runs/' . (int) $run['PK_MonitorRunID'])) ?>" style="font-size:.82rem;">details</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="card mb-4"><div class="card-body text-faint">No scheduled runs recorded yet.</div></div>
<?php endif; ?>

<!-- Recent failures -->
<h3 class="h5 mb-3">Recent failures</h3>
<?php if ($recentFailures !== []): ?>
    <div class="table-card mb-4">
        <table class="table">
            <thead><tr><th>When</th><th>Host</th><th>Source</th><th>Error</th></tr></thead>
            <tbody>
            <?php foreach ($recentFailures as $f): ?>
                <tr>
                    <td style="font-family:var(--font-mono);font-size:.82rem;white-space:nowrap;"><?= e(format_date($f['CheckedAt'], 'M j, g:i A')) ?></td>
                    <td><?= favicon_img($f['Host']) ?> <?= e($f['Label'] ?: $f['Host']) ?></td>
                    <td><span class="badge-soft<?= $f['Source'] === 'manual' ? ' is-active' : '' ?>"><?= e($f['Source']) ?></span></td>
                    <td class="text-muted-2" style="font-size:.85rem;"><?= e($f['ErrorText'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="card mb-4"><div class="card-body" style="color:var(--ok);">&#10003; No failed checks recorded.</div></div>
<?php endif; ?>

<!-- Targets + Users breakdown -->
<div class="row g-3">
    <div class="col-md-6"><div class="card h-100"><div class="card-body">
        <h3 class="h6 mb-3">Targets</h3>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <?php foreach (['healthy', 'warning', 'critical', 'expired', 'failed', 'unknown'] as $k): ?>
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
