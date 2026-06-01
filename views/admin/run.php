<?php
/**
 * ADMIN — one scheduled run plus exactly the checks it produced.
 * @var array $run  @var array $checks
 */
$num = fn ($v): string => number_format((int) $v);
?>
<div class="mb-4">
    <a class="text-muted-2" href="<?= e(url('/admin/runs')) ?>">&larr; Back to runs</a>
    <h1 class="mt-2 mb-1">Run #<?= (int) $run['PK_MonitorRunID'] ?></h1>
    <p class="text-muted-2 mb-0"><?= e(format_date($run['StartedAt'])) ?></p>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Mode</div>
        <div class="stat-value" style="font-size:1.2rem;"><?= $run['Mode'] === 'due' ? 'scheduled' : 'manual' ?></div>
        <?php if ($run['DueCount'] !== null): ?><div class="text-faint" style="font-size:.8rem;"><?= (int) $run['DueCount'] ?> due</div><?php endif; ?>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Checked</div><div class="stat-value"><?= $num($run['CheckedCount']) ?></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Result</div>
        <div class="stat-value"><span style="color:var(--ok)"><?= (int) $run['OkCount'] ?></span> <span class="text-faint">/</span> <span style="color:<?= (int) $run['FailedCount'] > 0 ? 'var(--danger)' : 'var(--text-faint)' ?>"><?= (int) $run['FailedCount'] ?></span></div>
        <div class="text-faint" style="font-size:.8rem;">ok / failed</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Duration</div><div class="stat-value" style="font-size:1.2rem;"><?= $num($run['DurationMs']) ?> ms</div>
    </div></div></div>
</div>

<h3 class="h5 mb-3">Targets checked (<?= count($checks) ?>)</h3>
<?php if ($checks !== []): ?>
    <div class="table-card">
        <table class="table">
            <thead><tr><th>Host</th><th>Result</th><th>Days left</th><th>Expires</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($checks as $c): ?>
                <?php $ok = (int) $c['IsOk'] === 1; ?>
                <tr>
                    <td><?= favicon_img($c['Host']) ?> <?= e($c['Label'] ?: $c['Host']) ?></td>
                    <td><span class="badge-soft is-<?= $ok ? 'healthy' : 'failed' ?>"><?= $ok ? 'ok' : 'failed' ?></span></td>
                    <td><?= $c['DaysLeft'] === null ? '<span class="text-faint">—</span>' : e(days_left_label((int) $c['DaysLeft'])) ?></td>
                    <td class="text-muted-2" style="font-size:.85rem;"><?= $c['ExpiresAt'] ? e(format_date($c['ExpiresAt'], 'M j, Y')) : '—' ?></td>
                    <td class="text-muted-2" style="font-size:.85rem;"><?= $ok ? e($c['Issuer'] ?? '') : e($c['ErrorText'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="card"><div class="card-body text-faint">No per-target checks linked to this run — e.g. a "nothing due" run, or a run from before per-run tracking was added.</div></div>
<?php endif; ?>
