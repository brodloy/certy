<?php
/**
 * DEMO SERVICE — the public, throwaway demo account behind the "Try the live
 * demo" button. `ensure()` find-or-creates the account; `reset()` restores it to
 * a known, varied set of targets (run nightly by `console demo:reset`) so the
 * demo always looks good no matter what a visitor did to it.
 *
 * The account (identified by config('demo_email')) is created VERIFIED with a
 * random, unknown password — so the only way in is the demo button; the /login
 * form can't be used for it. Its settings are locked (see SettingsController +
 * is_demo_user()).
 */
class DemoService
{
    /** Find the demo user, creating it (verified, random password) if absent. */
    public function ensure(): array
    {
        $email = $this->email();
        $user  = db()->first('SELECT * FROM `User` WHERE `Email` = ?', [$email]);
        if ($user !== null) {
            return $user;
        }
        $now = gmdate('Y-m-d H:i:s');
        db()->insert('User', [
            'Name'         => 'Demo User',
            'Email'        => $email,
            'PasswordHash' => password_hash(bin2hex(random_bytes(18)), PASSWORD_ARGON2ID),
            'Role'         => 'user',
            'Active'       => 1,
            'VerifiedAt'   => $now,
            'CreatedAt'    => $now,
            'UpdatedAt'    => $now,
        ]);
        return db()->first('SELECT * FROM `User` WHERE `Email` = ?', [$email]);
    }

    /**
     * Restore the demo account to the canonical target set and re-scan, so the
     * dashboard shows a healthy mix (healthy / expired / failed / domain) on
     * every visit. Safe to run repeatedly — the nightly cron does.
     */
    public function reset(): void
    {
        $uid = (int) $this->ensure()['PK_UserID'];

        // Clear whatever the last visitor left (cascades to CheckResults).
        db()->run('DELETE FROM `MonitoredTarget` WHERE `FK_UserID` = ?', [$uid]);

        $now = gmdate('Y-m-d H:i:s');
        $ssl = $this->typeId('ssl');
        $dom = $this->typeId('domain');

        // A deliberately varied set so the demo shows the colour-coding off:
        // healthy SSL, an expired cert, an untrusted cert (strict), and domains.
        // [type, host, port, label, verifyTls]
        $set = [
            ['ssl',    'github.com',             443, 'GitHub',                  0],
            ['ssl',    'cloudflare.com',         443, 'Cloudflare',              0],
            ['ssl',    'expired.badssl.com',     443, 'Expired cert (demo)',     0],
            ['ssl',    'self-signed.badssl.com', 443, 'Untrusted cert (strict)', 1],
            ['domain', 'bbc.co.uk',              443, 'bbc.co.uk',               0],
            ['domain', 'example.com',            443, 'example.com',             0],
        ];

        $ids = [];
        foreach ($set as [$type, $host, $port, $label, $verify]) {
            $ids[] = db()->insert('MonitoredTarget', [
                'FK_UserID'       => $uid,
                'FK_TargetTypeID' => $type === 'ssl' ? $ssl : $dom,
                'Host'            => $host,
                'Port'            => $port,
                'VerifyTls'       => $verify,
                'Label'           => $label,
                'IsActive'        => 1,
                'CreatedAt'       => $now,
                'UpdatedAt'       => $now,
            ]);
        }

        // Populate statuses so the demo dashboard isn't all "unchecked".
        (new MonitorService())->runChecks($ids, false, 'manual');
    }

    private function email(): string
    {
        return (string) config('demo_email', 'demo@example.com');
    }

    private function typeId(string $code): int
    {
        $row = db()->first('SELECT `PK_TargetTypeID` FROM `LK_TargetType` WHERE `Code` = ?', [$code]);
        return (int) ($row['PK_TargetTypeID'] ?? 1);
    }
}
