<?php
/** @var array $rows @var int $count @var int $max @var array $hosts @var string $fResult @var string $fHost */
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="mb-1">Targets</h1>
        <p class="text-muted-2 mb-0"><?= e((string) $count) ?> of <?= e((string) $max) ?> used</p>
    </div>
    <?php if ($count < $max): ?>
        <a class="btn btn-primary" href="<?= e(url('/targets/create')) ?>">+ Add target</a>
    <?php else: ?>
        <button class="btn btn-primary" disabled title="Limit reached">+ Add target</button>
    <?php endif; ?>
</div>

<?php if ($count > 0 && ($hosts ?? []) !== []): ?>
    <?php $action = '/targets'; include BASE_PATH . '/views/partials/filter-bar.php'; ?>
<?php endif; ?>

<?php if ($count === 0): ?>
    <div class="card"><div class="card-body text-center py-5">
        <p class="text-muted-2 mb-3">No targets yet.</p>
        <a class="btn btn-primary" href="<?= e(url('/targets/create')) ?>">Add your first target</a>
    </div></div>
<?php elseif ($rows === []): ?>
    <div class="card"><div class="card-body text-center py-5">
        <p class="text-muted-2 mb-3">No targets match these filters.</p>
        <a class="btn btn-outline-secondary" href="<?= e(url('/targets')) ?>">Clear filters</a>
    </div></div>
<?php else: ?>
    <?php foreach ($rows as $r):
        $isOk   = $r['LastIsOk'] === null ? null : (int) $r['LastIsOk'];
        $days   = $r['LastDaysLeft'] === null ? null : (int) $r['LastDaysLeft'];
        $status = monitor_status($isOk, $days);
    ?>
        <div class="list-row">
            <div>
                <a class="title d-inline-flex align-items-center gap-2" href="<?= e(url('/targets/' . $r['PK_MonitoredTargetID'])) ?>">
                    <?= favicon_img($r['Host']) ?><?= e($r['Host']) ?>
                </a>
                <div class="text-faint" style="font-size:.78rem;">
                    <?= e($r['TypeLabel']) ?><?= !empty($r['Label']) ? ' · ' . e($r['Label']) : '' ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge-soft is-<?= e($status) ?>"><?= e(strtolower(monitor_status_label($status))) ?></span>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/targets/' . $r['PK_MonitoredTargetID'] . '/edit')) ?>">Edit</a>
                <form method="post" action="<?= e(url('/targets/' . $r['PK_MonitoredTargetID'] . '/delete')) ?>"
                      onsubmit="return confirm('Delete this target and its history?');">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
