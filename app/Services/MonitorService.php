<?php
/**
 * MONITOR SERVICE — the orchestrator both triggers call (the "Check Now" button
 * and the scheduled run). It:
 *   1. loads the targets to check (all active, or a given subset by id);
 *   2. runs the right checker per target (ssl -> CertificateChecker, etc.);
 *   3. persists EVERY result to CheckResult (history);
 *   4. updates the denormalised Last* snapshot on MonitoredTarget (fast reads);
 *   5. returns the list of result-shaped arrays.
 *
 * It deliberately does NOT send alerts — that is AlertDispatcher's job, called
 * only by the scheduled trigger. This separation is the whole point: the button
 * and the cron share this identical call; the cron just does one extra step.
 *
 * Persistence is done with db() directly (the boilerplate's house style: the SQL
 * is right here where you can see it), rather than a separate repository class.
 */
class MonitorService
{
    private CertificateChecker $ssl;
    private DomainChecker $domain;

    public function __construct()
    {
        $this->ssl    = new CertificateChecker();
        $this->domain = new DomainChecker();
    }

    /**
     * Run checks and persist them.
     *
     * @param int[]|null $targetIds  null = every active target; otherwise just these ids.
     * @param bool       $retry      retry a FAILED check a few times before accepting
     *                               it (flap handling — see checkWithRetry). The
     *                               scheduled run uses this so a transient blip can't
     *                               fire a false failure alert; the interactive
     *                               dashboard scan passes false to stay snappy.
     * @return array<int, array>     list of result-shaped arrays (with 'target_id').
     */
    public function runChecks(?array $targetIds = null, bool $retry = true): array
    {
        $targets = $this->loadTargets($targetIds);
        $results = [];

        foreach ($targets as $t) {
            $result = $retry ? $this->checkWithRetry($t) : $this->runOne($t);
            $result['target_id'] = (int) $t['PK_MonitoredTargetID'];

            $this->persist((int) $t['PK_MonitoredTargetID'], $result);
            $results[] = $result;
        }

        return $results;
    }

    // --- internals -----------------------------------------------------------

    /** Run the right checker once for a target row. */
    private function runOne(array $t): array
    {
        return $t['TypeCode'] === 'ssl'   // joined from LK_TargetType
            ? $this->ssl->check($t['Host'], (int) $t['Port'], 8, (bool) ($t['VerifyTls'] ?? false))
            : $this->domain->check($t['Host']);
    }

    /**
     * Run a check, but if it FAILS, retry a few times with a short delay before
     * accepting the failure. A single transient network blip (a momentary DNS or
     * routing hiccup) should not flip a healthy target to 'failed' and email the
     * owner. Tunable via config: scan_retries (extra attempts), scan_retry_delay_ms.
     */
    private function checkWithRetry(array $t): array
    {
        $retries = max(0, (int) config('scan_retries', 2));
        $delayMs = max(0, (int) config('scan_retry_delay_ms', 1500));

        for ($attempt = 0; ; $attempt++) {
            $result = $this->runOne($t);
            if (!empty($result['ok']) || $attempt >= $retries) {
                return $result;
            }
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }
    }

    /** Load active targets (optionally a subset), with their type code joined in. */
    private function loadTargets(?array $targetIds): array
    {
        $sql = 'SELECT t.*, lt.`Code` AS `TypeCode`
                FROM `MonitoredTarget` t
                JOIN `LK_TargetType` lt ON lt.`PK_TargetTypeID` = t.`FK_TargetTypeID`
                WHERE t.`IsActive` = 1';
        $params = [];

        if ($targetIds !== null) {
            $ids = array_values(array_filter(array_map('intval', $targetIds)));
            if ($ids === []) {
                return [];
            }
            $holders = implode(', ', array_fill(0, count($ids), '?'));
            $sql    .= " AND t.`PK_MonitoredTargetID` IN ({$holders})";
            $params  = $ids;
        }

        $sql .= ' ORDER BY t.`PK_MonitoredTargetID`';
        return db()->all($sql, $params);
    }

    /** Write one history row AND refresh the denormalised snapshot on the target. */
    private function persist(int $targetId, array $r): void
    {
        $now      = gmdate('Y-m-d H:i:s');
        $isOk     = $r['ok'] ? 1 : 0;
        $expires  = ($r['ok'] && isset($r['expires_at'])) ? gmdate('Y-m-d H:i:s', $r['expires_at']) : null;
        $daysLeft = $r['ok'] ? ($r['days_left'] ?? null) : null;

        db()->insert('CheckResult', [
            'FK_MonitoredTargetID' => $targetId,
            'IsOk'                 => $isOk,
            'ExpiresAt'            => $expires,
            'DaysLeft'             => $daysLeft,
            'Issuer'               => $r['issuer']  ?? null,
            'Subject'              => $r['subject'] ?? null,
            'ErrorText'            => $r['ok'] ? null : ($r['error'] ?? 'unknown error'),
            'CheckedAt'            => $now,
        ]);

        db()->run(
            'UPDATE `MonitoredTarget`
                SET `LastCheckedAt` = ?, `LastIsOk` = ?, `LastExpiresAt` = ?,
                    `LastDaysLeft` = ?, `UpdatedAt` = ?
              WHERE `PK_MonitoredTargetID` = ?',
            [$now, $isOk, $expires, $daysLeft, $now, $targetId],
        );
    }
}
