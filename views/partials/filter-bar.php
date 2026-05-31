<?php
/**
 * Filter bar — result + host dropdowns that submit via GET, so filters live in
 * the URL (shareable, paginate-friendly, no JS needed). Used by the dashboard
 * target list.
 *
 * @var string $action   form action (the current list path, e.g. '/dashboard')
 * @var array  $hosts     distinct hosts to populate the host dropdown
 * @var string $fResult   currently selected result filter ('', 'ok', 'failed')
 * @var string $fHost     currently selected host filter ('' = all)
 * @var bool   $showResult whether to show the result filter (off where it makes no sense)
 */
$showResult = $showResult ?? true;
$active = ($fResult !== '' && $fResult !== null) || ($fHost !== '' && $fHost !== null);
?>
<form method="get" action="<?= e(url($action)) ?>" class="filter-bar" data-filter-bar>
    <?php if ($showResult): ?>
    <div>
        <label class="form-label">Result</label>
        <select class="form-select form-select-sm" name="result" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="ok"     <?= $fResult === 'ok'     ? 'selected' : '' ?>>Healthy / OK</option>
            <option value="failed" <?= $fResult === 'failed' ? 'selected' : '' ?>>Failed</option>
        </select>
    </div>
    <?php endif; ?>
    <div>
        <label class="form-label">Host</label>
        <select class="form-select form-select-sm" name="host" onchange="this.form.submit()">
            <option value="">All hosts</option>
            <?php foreach ($hosts as $h): ?>
                <option value="<?= e($h) ?>" <?= $fHost === $h ? 'selected' : '' ?>><?= e($h) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($active): ?>
        <a class="btn btn-outline-secondary btn-sm filter-clear" href="<?= e(url($action)) ?>">Clear</a>
    <?php endif; ?>
    <noscript><button class="btn btn-outline-secondary btn-sm" type="submit">Filter</button></noscript>
</form>
