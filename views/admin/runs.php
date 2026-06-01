<?php
/**
 * ADMIN — full scheduled-run log (paginated). @var array $result (db()->paginate)
 */
?>
<div class="mb-4">
    <a class="text-muted-2" href="<?= e(url('/admin')) ?>">&larr; Back to admin</a>
    <div class="d-flex align-items-center justify-content-between mt-2">
        <div>
            <h1 class="mb-1">Scan runs</h1>
            <p class="text-muted-2 mb-0"><?= number_format((int) $result['total']) ?> run(s) recorded — newest first.</p>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/admin/export')) ?>">Export CSV</a>
    </div>
</div>

<?php if ($result['rows'] !== []): ?>
    <div class="table-card">
        <table class="table">
            <thead><tr>
                <th>When</th><th>Mode</th><th>Due</th><th>Checked</th><th>Ok</th><th>Failed</th><th>Duration</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($result['rows'] as $run): ?>
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
    <?= pagination_links($result, '/admin/runs') ?>
<?php else: ?>
    <div class="card"><div class="card-body text-faint">No runs recorded yet.</div></div>
<?php endif; ?>
