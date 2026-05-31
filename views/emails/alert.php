<?php
/**
 * Alert email body (expiry or failure). Inline styles only.
 * @var string $accent @var string $statusText @var string $host @var string $label
 * @var string $intro @var array $rows @var string $ctaUrl @var string $ctaText
 */
?>
<div style="font-size:12px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:<?= e($accent) ?>;">
    <?= e($statusText) ?>
</div>
<div style="margin:8px 0 2px;font-size:21px;font-weight:600;font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace;color:#0f172a;word-break:break-all;">
    <?= e($host) ?>
</div>
<?php if (!empty($label)): ?>
    <div style="color:#64748b;font-size:13px;margin-bottom:4px;"><?= e($label) ?></div>
<?php endif; ?>
<p style="color:#475569;font-size:14px;line-height:1.65;margin:14px 0 18px;"><?= e($intro) ?></p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin-bottom:24px;">
    <?php foreach ($rows as $k => $v): ?>
        <tr>
            <td style="padding:6px 0;color:#94a3b8;width:120px;vertical-align:top;"><?= e((string) $k) ?></td>
            <td style="padding:6px 0;color:#0f172a;font-weight:500;"><?= e((string) $v) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<a href="<?= e($ctaUrl) ?>" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;padding:11px 22px;border-radius:10px;">
    <?= e($ctaText) ?>
</a>
