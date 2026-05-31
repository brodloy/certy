<?php /** @var string $token @var string $email */ ?>
<div class="auth-wrap"><div class="auth-card">
    <h1>Choose a new password</h1>
    <p class="auth-sub">Pick something secure you'll remember</p>
    <div class="card"><div class="card-body">
        <form method="post" action="<?= e(url('/reset')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="email" value="<?= e($email) ?>">
            <div class="mb-3"><label class="form-label">New password</label>
                <input class="form-control" type="password" name="password" autofocus>
                <div class="form-text">At least 8 characters.</div></div>
            <div class="mb-3"><label class="form-label">Confirm password</label>
                <input class="form-control" type="password" name="password_confirm"></div>
            <button class="btn btn-primary w-100" type="submit">Reset password</button>
        </form>
    </div></div>
</div></div>
