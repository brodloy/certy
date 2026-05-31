<?php
/**
 * EMAIL LAYOUT — a minimal, client-safe HTML shell (inline styles + tables,
 * no external CSS). Rendered by view('emails/<x>', $data, 'email').
 * @var string $content @var string $title @var string $preheader
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? config('app_name')) ?></title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;-webkit-font-smoothing:antialiased;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#0f172a;">
    <?php if (!empty($preheader)): ?>
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#f1f5f9;"><?= e($preheader) ?></div>
    <?php endif; ?>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;">
                <tr><td style="padding:18px 28px;border-bottom:1px solid #e2e8f0;font-weight:700;font-size:17px;color:#4f46e5;letter-spacing:-0.02em;">
                    <?= e(config('app_name')) ?>
                </td></tr>
                <tr><td style="padding:28px;"><?= $content ?></td></tr>
                <tr><td style="padding:16px 28px;border-top:1px solid #e2e8f0;font-size:12px;line-height:1.6;color:#94a3b8;">
                    You're receiving this because you monitor this host on <?= e(config('app_name')) ?>.
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
