<div class="auth-wrap"><div class="auth-card">
    <a href="<?= e(url('/')) ?>" class="auth-brand"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg><?= e(config('app_name')) ?></a>
    <h1>Welcome back</h1>
    <p class="auth-sub">Sign in to your <?= e(config('app_name')) ?> account</p>
    <div class="card"><div class="card-body">
        <form method="post" action="<?= e(url('/login')) ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input class="form-control" type="email" name="email" value="<?= e(old('email')) ?>" autofocus>
            </div>
            <div class="mb-3">
                <div class="d-flex justify-content-between">
                    <label class="form-label">Password</label>
                    <a class="small" href="<?= e(url('/forgot')) ?>">Forgot?</a>
                </div>
                <input class="form-control" type="password" name="password">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                <label class="form-check-label" for="remember" style="font-size:.9rem;">Remember me</label>
            </div>
            <button class="btn btn-primary w-100" type="submit">Sign in</button>
        </form>

        <?php if (config('demo_enabled', true)): ?>
            <div class="divider">or</div>
            <form method="post" action="<?= e(url('/demo')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-secondary w-100" type="submit">Explore the live demo</button>
            </form>
        <?php endif; ?>

        <?php if (config('google_enabled') || config('github_enabled')): ?>
            <div class="divider">or</div>
            <?php if (config('google_enabled')): ?>
                <a class="btn btn-google mb-2" href="<?= e(url('/auth/google')) ?>">
                    <svg width="16" height="16" viewBox="0 0 18 18" aria-hidden="true"><path fill="#4285F4" d="M17.6 9.2c0-.6-.1-1.2-.2-1.8H9v3.5h4.8a4.1 4.1 0 0 1-1.8 2.7v2.2h2.9c1.7-1.6 2.7-3.9 2.7-6.6z"/><path fill="#34A853" d="M9 18c2.4 0 4.5-.8 6-2.2l-2.9-2.2c-.8.5-1.8.9-3.1.9-2.4 0-4.4-1.6-5.1-3.8H.8v2.3A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.9 10.7a5.4 5.4 0 0 1 0-3.4V5H.8a9 9 0 0 0 0 8z"/><path fill="#EA4335" d="M9 3.6c1.3 0 2.5.5 3.4 1.3l2.6-2.6A9 9 0 0 0 .8 5l3.1 2.3C4.6 5.2 6.6 3.6 9 3.6z"/></svg>
                    Continue with Google
                </a>
            <?php endif; ?>
            <?php if (config('github_enabled')): ?>
                <a class="btn btn-github" href="<?= e(url('/auth/github')) ?>">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8z"/></svg>
                    Continue with GitHub
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div></div>
    <p class="text-center text-muted-2 mt-3 mb-0" style="font-size:.92rem;">
        New here? <a href="<?= e(url('/register')) ?>">Create an account</a>
    </p>
</div></div>
<?php clear_old(); ?>
