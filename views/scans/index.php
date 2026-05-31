<?php
/** @var array $rows @var int $total @var array $meta @var array $hosts @var string $fResult @var string $fHost */
$filtering = ($fResult !== '' || $fHost !== '');
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="mb-1">Scans</h1>
        <p class="text-muted-2 mb-0"><?= e((string) $total) ?> scan <?= $total === 1 ? 'result' : 'results' ?><?= $filtering ? ' (filtered)' : ' recorded' ?></p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" data-check-all data-reload-after>Scan all now</button>
        <a class="btn btn-primary" href="<?= e(url('/dashboard')) ?>">Go to dashboard</a>
    </div>
</div>

<?php if ($hosts !== []): ?>
    <?php $action = '/scans'; include BASE_PATH . '/views/partials/filter-bar.php'; ?>
<?php endif; ?>

<?php if ($rows === [] && $filtering): ?>
    <div class="card"><div class="card-body text-center py-5">
        <p class="text-muted-2 mb-3">No scans match these filters.</p>
        <a class="btn btn-outline-secondary" href="<?= e(url('/scans')) ?>">Clear filters</a>
    </div></div>
<?php elseif ($rows === []): ?>
    <div class="card"><div class="card-body text-center py-5">
        <p class="text-muted-2 mb-3">No scans have run yet.</p>
        <a class="btn btn-primary" href="<?= e(url('/dashboard')) ?>">Run a scan from the dashboard</a>
    </div></div>
<?php else: ?>
    <div class="table-card">
        <table class="table">
            <thead><tr>
                <th>When</th><th>Host</th><th>Type</th><th>Result</th>
                <th>Expires</th><th>Days left</th><th>Detail</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $ok   = (int) $r['IsOk'] === 1;
                $days = $r['DaysLeft'] === null ? null : (int) $r['DaysLeft'];
                $detail = $ok ? trim((string) ($r['Issuer'] ?? '') . ($r['Subject'] ? ' · ' . $r['Subject'] : '')) : (string) ($r['ErrorText'] ?? '');
            ?>
                <tr>
                    <td style="font-family:var(--font-mono);font-size:.8rem;white-space:nowrap;"><?= e(format_date($r['CheckedAt'])) ?></td>
                    <td>
                        <a class="host" href="<?= e(url('/targets/' . $r['FK_MonitoredTargetID'])) ?>"><?= e($r['Host']) ?></a>
                        <?php if (!empty($r['Label'])): ?><div class="text-faint" style="font-size:.74rem;"><?= e($r['Label']) ?></div><?php endif; ?>
                    </td>
                    <td><span class="badge-soft"><?= e($r['TypeCode']) ?></span></td>
                    <td><?= $ok ? '<span class="badge-soft is-healthy">ok</span>' : '<span class="badge-soft is-critical">failed</span>' ?></td>
                    <td><?= $r['ExpiresAt'] ? e(format_date($r['ExpiresAt'], 'M j, Y')) : '<span class="text-faint">—</span>' ?></td>
                    <td class="days-left"><?= $days === null ? '<span class="text-faint">—</span>' : e((string) $days) ?></td>
                    <td class="text-faint wrap" style="font-size:.8rem;max-width:220px;"><?= e($detail) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= pagination_links($meta, '/scans', ['result' => $fResult, 'host' => $fHost]) ?>
<?php endif; ?>
