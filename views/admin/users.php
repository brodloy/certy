<?php /** @var array $result */
$rows = $result['rows'];
$meId = (int) current_user()['PK_UserID'];
?>
<h1 class="mb-4">Users</h1>
<div class="table-card">
    <table class="table mb-0">
        <thead>
            <tr><th class="ps-3">Name</th><th>Email</th><th>Role</th><th>Verified</th><th>Status</th><th>Joined</th><th class="pe-3 text-end"></th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $u):
            $active = (int) $u['Active'] === 1;
            $isSelf = (int) $u['PK_UserID'] === $meId;
        ?>
            <tr>
                <td class="ps-3"><?= e($u['Name']) ?></td>
                <td><?= e($u['Email']) ?></td>
                <td><span class="badge-soft<?= $u['Role'] === 'admin' ? ' is-active' : '' ?>"><?= e($u['Role']) ?></span></td>
                <td><?= $u['VerifiedAt'] ? 'Yes' : '<span class="text-faint">No</span>' ?></td>
                <td><span class="badge-soft is-<?= $active ? 'healthy' : 'failed' ?>"><?= $active ? 'active' : 'disabled' ?></span></td>
                <td class="text-faint" style="font-size:.85rem;white-space:nowrap;"><?= e(format_date($u['CreatedAt'], 'M j, Y')) ?></td>
                <td class="pe-3 text-end">
                    <?php if ($isSelf): ?>
                        <span class="text-faint" style="font-size:.8rem;">you</span>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('/admin/users/' . $u['PK_UserID'] . '/toggle')) ?>"
                              onsubmit="return confirm('<?= $active ? 'Disable' : 'Enable' ?> this user?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm <?= $active ? 'btn-outline-danger' : 'btn-outline-secondary' ?>" type="submit">
                                <?= $active ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= pagination_links($result, '/admin/users') ?>
