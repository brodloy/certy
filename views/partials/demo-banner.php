<?php
/** Shown in the app area when signed in as the shared demo account. */
if (is_demo_user()):
?>
    <div class="flash flash-info d-flex align-items-center justify-content-between gap-2">
        <span>
            <strong>You're exploring the certy demo.</strong>
            It's fully functional — add targets, run checks, browse the history.
            It's a shared account, so it resets to a clean state nightly.
        </span>
        <form method="post" action="<?= e(url('/demo/reset')) ?>" class="m-0 flex-shrink-0">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-secondary" type="submit">Reset demo</button>
        </form>
    </div>
<?php endif; ?>
