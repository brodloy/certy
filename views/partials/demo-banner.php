<?php
/** Shown in the app area when signed in as the shared demo account. */
if (is_demo_user()):
?>
    <div class="flash flash-info">
        <strong>You're exploring the certy demo.</strong>
        It's fully functional — add targets, run checks, browse the history.
        This is a shared account, so it resets to a clean state nightly.
    </div>
<?php endif; ?>
