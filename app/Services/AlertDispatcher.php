<?php
/**
 * ALERT DISPATCHER — turns scan results into emails. Called ONLY by the
 * scheduled trigger (console monitor:run), never by the dashboard "Scan all"
 * (there the user is watching the screen). Two alert kinds:
 *
 *   expiry  — when a target's days_left has dropped into an active
 *             LK_AlertThreshold tier (30/14/7/1). Fires once per tier per
 *             certificate cycle: AlertLog dedup is keyed by the expiry it fired
 *             against (ExpiresAtSnapshot), so a renewal re-arms every tier.
 *   failure — when a check transitions from ok -> failed (host unreachable, no
 *             TLS). Fires once on entry to the failed state, not on every run.
 *
 * Only users with a verified email are notified. Returns the number of emails
 * sent. See docs/scheduling.md and docs/security.md.
 */
class AlertDispatcher
{
    private const TYPE_EXPIRY  = 1;   // LK_AlertType seed
    private const TYPE_FAILURE = 2;

    /** @param array<int,array> $results result-shaped arrays from MonitorService */
    public function dispatch(array $results): int
    {
        if ($results === []) {
            return 0;
        }

        $thresholds = db()->all(
            'SELECT `PK_AlertThresholdID` AS id, `Days` AS days
               FROM `LK_AlertThreshold` WHERE `IsActive` = 1 ORDER BY `Days` ASC',
        );

        $sent = 0;
        foreach ($results as $r) {
            $target = $this->targetWithOwner((int) ($r['target_id'] ?? 0));
            if ($target === null || empty($target['VerifiedAt'])) {
                continue; // unknown target, or the owner hasn't verified their email
            }
            if ($target['Email'] === config('demo_email', 'demo@example.com')) {
                continue; // never email the shared demo account (its address is a placeholder)
            }

            $sent += !empty($r['ok'])
                ? $this->maybeExpiryAlert($target, $r, $thresholds)
                : $this->maybeFailureAlert($target, $r);
        }
        return $sent;
    }

    // --- expiry ---------------------------------------------------------------

    private function maybeExpiryAlert(array $target, array $r, array $thresholds): int
    {
        $daysLeft = $r['days_left'] ?? null;
        if ($daysLeft === null || !isset($r['expires_at'])) {
            return 0; // ok but no expiry parsed — nothing to warn about
        }

        // The smallest active tier the cert has fallen into (6 days -> the 7 tier).
        $tier = null;
        foreach ($thresholds as $th) {
            if ((int) $daysLeft <= (int) $th['days']) {
                $tier = $th;
                break;
            }
        }
        if ($tier === null) {
            return 0; // still comfortably in the future
        }

        $snapshot = gmdate('Y-m-d H:i:s', (int) $r['expires_at']);
        $targetId = (int) $target['PK_MonitoredTargetID'];
        if ($this->alreadySent($targetId, self::TYPE_EXPIRY, (int) $tier['id'], $snapshot)) {
            return 0;
        }

        $this->sendExpiry($target, (int) $daysLeft, $snapshot);
        $this->log($targetId, self::TYPE_EXPIRY, (int) $tier['id'], $snapshot);
        return 1;
    }

    // --- failure --------------------------------------------------------------

    private function maybeFailureAlert(array $target, array $r): int
    {
        $targetId = (int) $target['PK_MonitoredTargetID'];

        // Alert only on the transition INTO failure. The failed check we're
        // reacting to is already the most-recent CheckResult row, so look at the
        // one before it: if that was ok (or there is none), this is a new outage.
        $prev = db()->first(
            'SELECT `IsOk` FROM `CheckResult`
              WHERE `FK_MonitoredTargetID` = ?
              ORDER BY `PK_CheckResultID` DESC LIMIT 1 OFFSET 1',
            [$targetId],
        );
        $newlyFailed = $prev === null || (int) $prev['IsOk'] === 1;
        if (!$newlyFailed) {
            return 0; // still down — already alerted on the way in
        }

        $this->sendFailure($target, (string) ($r['error'] ?? 'check failed'));
        $this->log($targetId, self::TYPE_FAILURE, null, null);
        return 1;
    }

    // --- email building -------------------------------------------------------

    private function sendExpiry(array $target, int $daysLeft, string $snapshot): void
    {
        $host    = (string) $target['Host'];
        $expired = $daysLeft < 0;
        $subject = $expired
            ? "{$host} certificate has expired"
            : "{$host} certificate expires in {$daysLeft} day" . ($daysLeft === 1 ? '' : 's');
        $intro = $expired
            ? "The TLS certificate for {$host} has expired. Renew it as soon as possible."
            : "The TLS certificate for {$host} expires soon — renew it before it lapses.";
        $rows = [
            'Expires'   => format_date($snapshot, 'M j, Y'),
            'Days left' => days_left_label($daysLeft),
        ];
        $this->send($target, $subject, $expired ? 'Certificate expired' : 'Certificate expiring',
            $daysLeft <= 7 ? '#dc2626' : '#d97706', $intro, $rows);
    }

    private function sendFailure(array $target, string $error): void
    {
        $host    = (string) $target['Host'];
        $subject = "{$host} check failed";
        $intro   = config('app_name') . " could not complete its latest check of {$host}. It may be "
            . "unreachable or not serving TLS on the expected port.";
        $rows = ['Error' => $error, 'Type' => strtoupper((string) $target['TypeCode'])];
        $this->send($target, $subject, 'Check failed', '#dc2626', $intro, $rows);
    }

    private function send(array $target, string $subject, string $statusText, string $accent, string $intro, array $rows): void
    {
        $ctaUrl = url('/targets/' . $target['PK_MonitoredTargetID']);
        $data = [
            'title'      => $subject,
            'preheader'  => $intro,
            'accent'     => $accent,
            'statusText' => $statusText,
            'host'       => (string) $target['Host'],
            'label'      => (string) ($target['Label'] ?? ''),
            'intro'      => $intro,
            'rows'       => $rows,
            'ctaUrl'     => $ctaUrl,
            'ctaText'    => 'View on ' . config('app_name'),
        ];
        $html = view('emails/alert', $data, 'email');
        send_mail((string) $target['Email'], $subject, $this->plainText($statusText, (string) $target['Host'], $intro, $rows, $ctaUrl), $html);
    }

    private function plainText(string $statusText, string $host, string $intro, array $rows, string $ctaUrl): string
    {
        $lines = [strtoupper($statusText), $host, '', $intro, ''];
        foreach ($rows as $k => $v) {
            $lines[] = "{$k}: {$v}";
        }
        $lines[] = '';
        $lines[] = 'View: ' . $ctaUrl;
        return implode("\n", $lines) . "\n";
    }

    // --- data + ledger --------------------------------------------------------

    private function targetWithOwner(int $targetId): ?array
    {
        return db()->first(
            'SELECT t.*, lt.`Code` AS `TypeCode`, u.`Email`, u.`VerifiedAt`, u.`Name`
               FROM `MonitoredTarget` t
               JOIN `LK_TargetType` lt ON lt.`PK_TargetTypeID` = t.`FK_TargetTypeID`
               JOIN `User` u           ON u.`PK_UserID` = t.`FK_UserID`
              WHERE t.`PK_MonitoredTargetID` = ?',
            [$targetId],
        );
    }

    private function alreadySent(int $targetId, int $type, ?int $thresholdId, ?string $snapshot): bool
    {
        // <=> is the NULL-safe equals, so null threshold/snapshot match correctly.
        return db()->first(
            'SELECT 1 FROM `AlertLog`
              WHERE `FK_MonitoredTargetID` = ? AND `FK_AlertTypeID` = ?
                AND `FK_AlertThresholdID` <=> ? AND `ExpiresAtSnapshot` <=> ?
              LIMIT 1',
            [$targetId, $type, $thresholdId, $snapshot],
        ) !== null;
    }

    private function log(int $targetId, int $type, ?int $thresholdId, ?string $snapshot): void
    {
        db()->insert('AlertLog', [
            'FK_MonitoredTargetID' => $targetId,
            'FK_AlertTypeID'       => $type,
            'FK_AlertThresholdID'  => $thresholdId,
            'ExpiresAtSnapshot'    => $snapshot,
            'SentAt'               => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
