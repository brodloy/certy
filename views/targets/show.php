<?php
/** Per-target history timeline. @var array $target @var array $history */
$isOk   = $target['LastIsOk'] === null ? null : (int) $target['LastIsOk'];
$days   = $target['LastDaysLeft'] === null ? null : (int) $target['LastDaysLeft'];
$status = monitor_status($isOk, $days);
?>
<div class="mb-3"><a class="text-muted-2" href="<?= e(url('/dashboard')) ?>">&larr; Back to dashboard</a></div>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="mb-1" style="font-family:var(--font-mono);font-size:1.6rem;"><?= e($target['Host']) ?></h1>
        <p class="text-muted-2 mb-0">
            <?= e($target['TypeLabel']) ?><?= !empty($target['Label']) ? ' · ' . e($target['Label']) : '' ?>
            <?php if ($target['TypeCode'] === 'ssl' && (int) $target['Port'] !== 443): ?> · port <?= e((string) $target['Port']) ?><?php endif; ?>
        </p>
    </div>
    <span class="badge-soft is-<?= e($status) ?>"><?= e(strtolower(monitor_status_label($status))) ?></span>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6"><div class="card h-100"><div class="card-body">
        <div class="stat-label">Expires</div>
        <div class="fw-medium mt-1" style="font-size:1.1rem;"><?= $target['LastExpiresAt'] ? e(format_date($target['LastExpiresAt'], 'M j, Y')) : '—' ?></div>
        <div class="text-muted-2" style="font-size:.9rem;"><?= $days === null ? 'not checked yet' : e((string) $days) . ' days left' ?></div>
    </div></div></div>
    <div class="col-sm-6"><div class="card h-100"><div class="card-body">
        <div class="stat-label">Last checked</div>
        <div class="fw-medium mt-1" style="font-size:1.1rem;"><?= $target['LastCheckedAt'] ? e(format_date($target['LastCheckedAt'])) : 'never' ?></div>
        <button class="btn btn-outline-secondary btn-sm mt-2" data-check="<?= e((string) $target['PK_MonitoredTargetID']) ?>">Scan</button>
    </div></div></div>
</div>

<h3 class="mb-3">History</h3>
<?php if ($history === []): ?>
    <div class="card"><div class="card-body text-muted-2">No checks recorded yet. Run a check to start the timeline.</div></div>
<?php else: ?>
    <div class="table-card">
        <table class="table">
            <thead><tr><th>Checked</th><th>Result</th><th>Expires</th><th>Days left</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td style="font-family:var(--font-mono);font-size:.82rem;"><?= e(format_date($h['CheckedAt'])) ?></td>
                    <td><?= ((int) $h['IsOk'] === 1)
                        ? '<span class="badge-soft is-healthy">ok</span>'
                        : '<span class="badge-soft is-critical">failed</span>' ?></td>
                    <td><?= $h['ExpiresAt'] ? e(format_date($h['ExpiresAt'], 'M j, Y')) : '<span class="text-faint">—</span>' ?></td>
                    <td class="days-left"><?= $h['DaysLeft'] === null ? '<span class="text-faint">—</span>' : e((string) (int) $h['DaysLeft']) ?></td>
                    <td class="text-faint" style="font-size:.82rem;">
                        <?php if ((int) $h['IsOk'] === 1): ?>
                            <?= e($h['Issuer'] ?? $h['Subject'] ?? '') ?>
                        <?php else: ?>
                            <?= e($h['ErrorText'] ?? '') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
