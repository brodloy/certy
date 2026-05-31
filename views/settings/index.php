<?php
/** @var array $user @var array $providers @var bool $hasPassword @var int $targetCount */
$isConnected = fn(string $p) => in_array($p, $providers, true);
$googleOn = (bool) config('google_enabled');
$githubOn = (bool) config('github_enabled');
?>
<h1 class="mb-1">Account settings</h1>
<p class="text-muted-2 mb-4">Manage your profile, sign-in methods, and account.</p>

<div style="max-width:760px;">

    <!-- Account overview -->
    <div class="card mb-4"><div class="card-body">
        <h3 class="mb-3" style="font-size:1.15rem;">Account</h3>
        <div class="row g-3" style="font-size:.95rem;">
            <div class="col-sm-6">
                <div class="stat-label mb-1">Plan</div>
                <div class="fw-medium">Free <span class="text-faint">— up to 10 targets</span></div>
            </div>
            <div class="col-sm-6">
                <div class="stat-label mb-1">Targets in use</div>
                <div class="fw-medium"><?= e((string) $targetCount) ?> of 10</div>
            </div>
            <div class="col-sm-6">
                <div class="stat-label mb-1">Email status</div>
                <div class="fw-medium">
                    <?php if (!empty($user['VerifiedAt'])): ?>
                        <span class="badge-soft is-healthy">verified</span>
                    <?php else: ?>
                        <span class="badge-soft is-warning">unverified</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="stat-label mb-1">Member since</div>
                <div class="fw-medium"><?= e(format_date($user['CreatedAt'], 'M j, Y')) ?></div>
            </div>
        </div>
    </div></div>

    <!-- Profile -->
    <div class="card mb-4"><div class="card-body">
        <h3 class="mb-3" style="font-size:1.15rem;">Profile</h3>
        <form method="post" action="<?= e(url('/settings/profile')) ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input class="form-control<?= invalid_class('name') ?>" type="text" name="name"
                       value="<?= e(old('name', $user['Name'])) ?>">
                <?= field_error('name') ?>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input class="form-control<?= invalid_class('email') ?>" type="email" name="email"
                       value="<?= e(old('email', $user['Email'])) ?>">
                <?= field_error('email') ?>
            </div>
            <button class="btn btn-primary" type="submit">Save profile</button>
        </form>
    </div></div>

    <!-- Connected accounts -->
    <?php if ($googleOn || $githubOn): ?>
    <div class="card mb-4"><div class="card-body">
        <h3 class="mb-1" style="font-size:1.15rem;">Connected accounts</h3>
        <p class="text-muted-2 mb-3" style="font-size:.92rem;">Sign in faster by linking a provider.</p>

        <?php if ($googleOn): ?>
        <div class="list-row mb-2">
            <div class="d-flex align-items-center gap-2">
                <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><path fill="#4285F4" d="M17.6 9.2c0-.6-.1-1.2-.2-1.8H9v3.5h4.8a4.1 4.1 0 0 1-1.8 2.7v2.2h2.9c1.7-1.6 2.7-3.9 2.7-6.6z"/><path fill="#34A853" d="M9 18c2.4 0 4.5-.8 6-2.2l-2.9-2.2c-.8.5-1.8.9-3.1.9-2.4 0-4.4-1.6-5.1-3.8H.8v2.3A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.9 10.7a5.4 5.4 0 0 1 0-3.4V5H.8a9 9 0 0 0 0 8z"/><path fill="#EA4335" d="M9 3.6c1.3 0 2.5.5 3.4 1.3l2.6-2.6A9 9 0 0 0 .8 5l3.1 2.3C4.6 5.2 6.6 3.6 9 3.6z"/></svg>
                <span class="fw-medium">Google</span>
                <?php if ($isConnected('google')): ?><span class="badge-soft is-healthy">connected</span><?php endif; ?>
            </div>
            <?php if ($isConnected('google')): ?>
                <form method="post" action="<?= e(url('/settings/disconnect')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="provider" value="google">
                    <button class="btn btn-outline-secondary btn-sm" type="submit">Disconnect</button>
                </form>
            <?php else: ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/auth/google')) ?>">Connect</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($githubOn): ?>
        <div class="list-row">
            <div class="d-flex align-items-center gap-2">
                <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8z"/></svg>
                <span class="fw-medium">GitHub</span>
                <?php if ($isConnected('github')): ?><span class="badge-soft is-healthy">connected</span><?php endif; ?>
            </div>
            <?php if ($isConnected('github')): ?>
                <form method="post" action="<?= e(url('/settings/disconnect')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="provider" value="github">
                    <button class="btn btn-outline-secondary btn-sm" type="submit">Disconnect</button>
                </form>
            <?php else: ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/auth/github')) ?>">Connect</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>

    <!-- Password -->
    <div class="card mb-4"><div class="card-body">
        <h3 class="mb-1" style="font-size:1.15rem;">Password</h3>
        <?php if (!$hasPassword): ?>
            <p class="text-muted-2 mb-3" style="font-size:.92rem;">
                Your account uses social sign-in only. Set a password below so you can also sign in with your email.
            </p>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/settings/password')) ?>">
            <?= csrf_field() ?>
            <?php if ($hasPassword): ?>
            <div class="mb-3">
                <label class="form-label">Current password</label>
                <input class="form-control<?= invalid_class('current_password') ?>" type="password" name="current_password">
                <?= field_error('current_password') ?>
            </div>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label"><?= $hasPassword ? 'New password' : 'Password' ?></label>
                <input class="form-control<?= invalid_class('new_password') ?>" type="password" name="new_password">
                <div class="form-text">At least 8 characters.</div>
                <?= field_error('new_password') ?>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm <?= $hasPassword ? 'new ' : '' ?>password</label>
                <input class="form-control<?= invalid_class('new_password_confirm') ?>" type="password" name="new_password_confirm">
                <?= field_error('new_password_confirm') ?>
            </div>
            <button class="btn btn-primary" type="submit"><?= $hasPassword ? 'Change password' : 'Set password' ?></button>
        </form>
    </div></div>

    <!-- Danger zone -->
    <div class="card mb-4" style="border-color: rgba(220,38,38,0.30);"><div class="card-body">
        <h3 class="mb-1" style="font-size:1.15rem; color: var(--danger);">Delete account</h3>
        <p class="text-muted-2 mb-3" style="font-size:.92rem;">
            Permanently deletes your account and all <?= e((string) $targetCount) ?> of your targets and their scan history.
            This cannot be undone.
        </p>
        <details>
            <summary class="btn btn-outline-danger btn-sm" style="display:inline-block; list-style:none; cursor:pointer;">Delete my account…</summary>
            <form method="post" action="<?= e(url('/settings/delete')) ?>" class="mt-3"
                  onsubmit="return confirm('This permanently deletes your account and all data. Continue?');">
                <?= csrf_field() ?>
                <?php if ($hasPassword): ?>
                <div class="mb-3" style="max-width:340px;">
                    <label class="form-label">Confirm your password</label>
                    <input class="form-control" type="password" name="password">
                </div>
                <?php endif; ?>
                <div class="mb-3" style="max-width:340px;">
                    <label class="form-label">Type DELETE to confirm</label>
                    <input class="form-control mono" type="text" name="confirm" placeholder="DELETE" autocomplete="off">
                </div>
                <button class="btn btn-outline-danger" type="submit">Permanently delete account</button>
            </form>
        </details>
    </div></div>

</div>
<?php clear_old(); ?>
