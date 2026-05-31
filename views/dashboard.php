<?php
/**
 * DASHBOARD — colour-coded overview of the user's monitored targets.
 * @var array $user @var array $rows @var array $tally @var int $count @var int $max
 */
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
            <button class="btn btn-outline-secondary" data-check-all>Scan all</button>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= e(url('/targets/create')) ?>">+ Add target</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-3 col-6"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Healthy</div><div class="stat-value" data-kpi="healthy" style="color:var(--ok)"><?= e((string) $tally['healthy']) ?></div>
    </div></div></div>
    <div class="col-sm-3 col-6"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Expiring soon</div><div class="stat-value" data-kpi="warning" style="color:var(--warn)"><?= e((string) $tally['warning']) ?></div>
    </div></div></div>
    <div class="col-sm-3 col-6"><div class="card stat-card h-100"><div class="card-body">
        <div class="stat-label">Critical</div><div class="stat-value" data-kpi="critical" style="color:var(--danger)"><?= e((string) $tally['critical']) ?></div>
    </div></div></div>
    <div class="col-sm-3 col-6"><div class="card stat-card h-100"><div class="card-body">
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
                <th>Host</th><th>Type</th><th>Expires</th><th>Days left</th>
                <th>Last checked</th><th>Status</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $isOk    = $r['LastIsOk'] === null ? null : (int) $r['LastIsOk'];
                $days    = $r['LastDaysLeft'] === null ? null : (int) $r['LastDaysLeft'];
                $status  = monitor_status($isOk, $days);
                $colour  = ['healthy' => 'ok', 'warning' => 'warn', 'critical' => 'danger', 'unknown' => 'neutral'][$status];
                $id      = (int) $r['PK_MonitoredTargetID'];
            ?>
                <tr data-row="<?= e((string) $id) ?>">
                    <td>
                        <a class="host" href="<?= e(url('/targets/' . $id)) ?>"><?= e($r['Host']) ?></a>
                        <?php if (!empty($r['Label'])): ?>
                            <div class="text-faint" style="font-size:.78rem;"><?= e($r['Label']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge-soft"><?= e($r['TypeCode']) ?></span></td>
                    <td data-cell="expires"><?= $r['LastExpiresAt'] ? e(format_date($r['LastExpiresAt'], 'M j, Y')) : '<span class="text-faint">—</span>' ?></td>
                    <td class="days-left" data-cell="days" style="color:var(--<?= e($colour) ?>)"><?= $days === null ? '<span class="text-faint">—</span>' : e((string) $days) . ' days' ?></td>
                    <td class="text-faint" data-cell="checked" style="font-family:var(--font-mono);font-size:.78rem;">
                        <?= $r['LastCheckedAt'] ? e(format_date($r['LastCheckedAt'], 'M j, g:i A')) : 'never' ?>
                    </td>
                    <td data-cell="status"><?= status_badge($status) ?></td>
                    <td class="text-end">
                        <button class="btn btn-outline-secondary btn-sm" data-check="<?= e((string) $id) ?>">Scan</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
