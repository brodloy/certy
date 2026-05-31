<?php
/**
 * Add / edit form. @var array|null $target
 * In edit mode ($target set) we prefill from the target but let old() override
 * (so a failed validation re-shows what the user just typed).
 */
$editing = $target !== null;
$tid     = $editing ? (int) $target['PK_MonitoredTargetID'] : 0;

$host  = old('host')  ?: ($editing ? $target['Host'] : '');
$type  = old('type')  ?: ($editing ? $target['TypeCode'] : 'ssl');
$label = old('label') ?: ($editing ? (string) $target['Label'] : '');
$port  = old('port')  ?: ($editing ? (string) $target['Port'] : '443');
$active = $editing ? ((int) $target['IsActive'] === 1) : true;

$action = $editing ? url('/targets/' . $tid) : url('/targets');
?>
<div class="mb-3"><a class="text-muted-2" href="<?= e(url('/targets')) ?>">&larr; Back to targets</a></div>
<h1 class="mb-4"><?= $editing ? 'Edit target' : 'Add a target' ?></h1>

<div class="card" style="max-width:620px;"><div class="card-body">
    <form method="post" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Host or domain</label>
            <input class="form-control mono<?= invalid_class('host') ?>" type="text" name="host"
                   value="<?= e($host) ?>" placeholder="example.com" autofocus>
            <?= field_error('host') ?>
            <div class="form-text">Paste a URL if you like — we'll tidy it to a bare host.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">What to check</label>
            <select class="form-select" name="type" data-type-select>
                <option value="ssl"    <?= $type === 'ssl'    ? 'selected' : '' ?>>SSL certificate expiry</option>
                <option value="domain" <?= $type === 'domain' ? 'selected' : '' ?>>Domain registration expiry</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Label <span class="text-faint">(optional)</span></label>
            <input class="form-control<?= invalid_class('label') ?>" type="text" name="label"
                   value="<?= e($label) ?>" placeholder="e.g. Main site">
        </div>
        <div class="mb-3" data-port-field<?= $type === 'domain' ? ' style="display:none;"' : '' ?>>
            <label class="form-label">Port <span class="text-faint">(SSL only — default 443)</span></label>
            <input class="form-control mono" type="number" name="port" value="<?= e($port) ?>" min="1" max="65535" style="max-width:140px;">
        </div>
        <?php if ($editing): ?>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" <?= $active ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active" style="font-size:.92rem;">
                Active <span class="text-faint">— scans run on this target; uncheck to pause it</span>
            </label>
        </div>
        <?php endif; ?>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Add target' ?></button>
            <a class="btn btn-outline-secondary" href="<?= e(url('/targets')) ?>">Cancel</a>
        </div>
    </form>
</div></div>
<?php clear_old(); ?>
