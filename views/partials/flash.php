<?php
/** Renders any one-shot flash messages, then they're gone. */
foreach (flash_all() as $type => $message):
    $class = $type === 'error' ? 'flash-error' : ($type === 'success' ? 'flash-success' : 'flash-info');
?>
    <div class="flash <?= e($class) ?>"><?= e($message) ?></div>
<?php endforeach; ?>
