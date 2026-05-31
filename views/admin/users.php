<?php /** @var array $result */
$rows = $result['rows']; ?>
<h1 class="mb-4">Users</h1>
<div class="card"><div class="card-body p-0">
    <table class="table mb-0">
        <thead>
            <tr><th class="ps-3">Name</th><th>Email</th><th>Role</th><th>Verified</th><th class="pe-3">Joined</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $u): ?>
            <tr>
                <td class="ps-3"><?= e($u['Name']) ?></td>
                <td><?= e($u['Email']) ?></td>
                <td><span class="badge-soft<?= $u['Role'] === 'admin' ? ' is-active' : '' ?>"><?= e($u['Role']) ?></span></td>
                <td><?= $u['VerifiedAt'] ? 'Yes' : '<span class="text-faint">No</span>' ?></td>
                <td class="pe-3 text-faint" style="font-size:.85rem;"><?= e(format_date($u['CreatedAt'], 'M j, Y')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
<?= pagination_links($result, '/admin/users') ?>
