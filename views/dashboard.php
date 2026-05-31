<?php
/**
 * DASHBOARD — colour-coded overview of the user's monitored targets.
 * @var array $user @var array $rows @var array $tally @var int $count @var int $max
 */
// Small inline icon (stroke = currentColor) for the per-row action buttons.
$ico = fn (string $body): string =>
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
    . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="mb-1">Your monitors</h1>
        <p class="text-muted-2 mb-0">
            <?= e((string) $count) ?> of <?= e((string) $max) ?> targets in use<?php
            if ($tally['critical'] > 0): ?> · <span style="color:var(--danger)"><?= e((string) $tally['critical']) ?> critical</span><?php
            elseif ($tally['warning'] > 0): ?> · <span style="color:var(--warn)"><?= e((string) $tally['warning']) ?> expiring soon</span><?php
            endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($count > 0): ?>
            <a class="btn btn-outline-secondary" href="<?= e(url('/targets/export')) ?>">Export CSV</a>
            <button class="btn btn-outline-secondary" data-check-all>Scan all</button>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= e(url('/targets/create')) ?>">+ Add target</a>
    </div>
</div>

<div class="row g-3 mb-4 row-cols-2 row-cols-md-3">
    <div class="col"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Healthy</div><div class="stat-value" data-kpi="healthy" style="color:var(--ok)"><?= e((string) $tally['healthy']) ?></div>
    </div></div></div>
    <div class="col"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Expiring soon</div><div class="stat-value" data-kpi="warning" style="color:var(--warn)"><?= e((string) $tally['warning']) ?></div>
    </div></div></div>
    <div class="col"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Critical</div><div class="stat-value" data-kpi="critical" style="color:var(--danger)"><?= e((string) $tally['critical']) ?></div>
    </div></div></div>
    <div class="col"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Expired</div><div class="stat-value" data-kpi="expired" style="color:var(--danger)"><?= e((string) $tally['expired']) ?></div>
    </div></div></div>
    <div class="col"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Failed</div><div class="stat-value" data-kpi="failed" style="color:var(--danger)"><?= e((string) $tally['failed']) ?></div>
    </div></div></div>
    <div class="col"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Unchecked</div><div class="stat-value" data-kpi="unknown" style="color:var(--neutral)"><?= e((string) $tally['unknown']) ?></div>
    </div></div></div>
</div>

<?php if ($count > 0 && ($hosts ?? []) !== []): ?>
    <?php $action = '/dashboard'; include BASE_PATH . '/views/partials/filter-bar.php'; ?>
<?php endif; ?>

<?php if ($count === 0): ?>
    <div class="card"><div class="card-body text-center py-5">
        <p class="text-muted-2 mb-3">You're not monitoring anything yet.</p>
        <a class="btn btn-primary" href="<?= e(url('/targets/create')) ?>">Add your first target</a>
    </div></div>
<?php elseif ($rows === []): ?>
    <div class="card"><div class="card-body text-center py-5">
        <p class="text-muted-2 mb-3">No targets match these filters.</p>
        <a class="btn btn-outline-secondary" href="<?= e(url('/dashboard')) ?>">Clear filters</a>
    </div></div>
<?php else: ?>
    <div class="table-card">
        <table class="table" id="monitorTable">
            <thead><tr>
                <th class="col-host">Host</th><th class="col-type">Type</th><th>Expires</th>
                <th class="col-checked">Last checked</th><th>Status</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $isOk    = $r['LastIsOk'] === null ? null : (int) $r['LastIsOk'];
                $days    = $r['LastDaysLeft'] === null ? null : (int) $r['LastDaysLeft'];
                $status  = monitor_status($isOk, $days);
                $colour  = ['healthy' => 'ok', 'warning' => 'warn', 'critical' => 'danger', 'expired' => 'danger', 'failed' => 'danger', 'unknown' => 'neutral'][$status];
                $id      = (int) $r['PK_MonitoredTargetID'];
            ?>
                <tr data-row="<?= e((string) $id) ?>">
                    <td class="col-host">
                        <a class="host d-inline-flex align-items-center gap-2" href="<?= e(url('/targets/' . $id)) ?>">
                            <?= favicon_img($r['Host']) ?><?= e($r['Host']) ?>
                        </a>
                        <?php if (!empty($r['Label'])): ?>
                            <div class="text-faint" style="font-size:.78rem;"><?= e($r['Label']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="col-type"><span class="badge-soft"><?= e($r['TypeCode']) ?></span></td>
                    <td>
                        <div data-cell="expires"><?= $r['LastExpiresAt'] ? e(format_date($r['LastExpiresAt'], 'M j, Y')) : '<span class="text-faint">—</span>' ?></div>
                        <div class="days-left" data-cell="days" style="font-size:.8rem;color:var(--<?= e($colour) ?>)"><?= $days === null ? '<span class="text-faint">' . ($status === 'failed' ? 'check failed' : 'not checked') . '</span>' : e(days_left_label($days)) ?></div>
                    </td>
                    <td class="col-checked text-faint" data-cell="checked" style="font-family:var(--font-mono);font-size:.78rem;">
                        <?= $r['LastCheckedAt'] ? e(format_date($r['LastCheckedAt'], 'M j, g:i A')) : 'never' ?>
                    </td>
                    <td data-cell="status"><?= status_badge($status) ?></td>
                    <td class="text-end">
                        <div class="d-inline-flex align-items-center gap-2">
                            <button class="btn-icon" type="button" data-check="<?= e((string) $id) ?>" data-icon title="Scan now" aria-label="Scan now"><?= $ico('<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>') ?></button>
                            <a class="btn-icon" href="<?= e(url('/targets/' . $id . '/edit')) ?>" title="Edit" aria-label="Edit"><?= $ico('<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>') ?></a>
                            <form method="post" action="<?= e(url('/targets/' . $id . '/delete')) ?>"
                                  onsubmit="return confirm('Delete this target and its history?');">
                                <?= csrf_field() ?>
                                <button class="btn-icon btn-icon-danger" type="submit" title="Delete" aria-label="Delete"><?= $ico('<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>') ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
