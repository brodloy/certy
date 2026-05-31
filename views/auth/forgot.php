<div class="auth-wrap"><div class="auth-card">
    <h1>Reset your password</h1>
    <p class="auth-sub">We'll email you a link to choose a new one</p>
    <div class="card"><div class="card-body">
        <form method="post" action="<?= e(url('/forgot')) ?>">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">Email</label>
                <input class="form-control" type="email" name="email" autofocus></div>
            <button class="btn btn-primary w-100" type="submit">Send reset link</button>
        </form>
    </div></div>
    <p class="text-center text-muted-2 mt-3 mb-0" style="font-size:.92rem;">
        <a href="<?= e(url('/login')) ?>">Back to sign in</a>
    </p>
</div></div>
