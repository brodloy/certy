<?php
/** Shown in the app area when the logged-in user hasn't verified their email. */
if (current_user() !== null && empty(current_user()['VerifiedAt'])):
?>
    <div class="flash flash-info d-flex align-items-center justify-content-between gap-2">
        <span>Please verify your email address.</span>
        <form method="post" action="<?= e(url('/verify/resend')) ?>" class="m-0">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-secondary" type="submit">Resend link</button>
        </form>
    </div>
<?php endif; ?>
