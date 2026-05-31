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
     * @return array<int, array>     list of result-shaped arrays (with 'target_id').
     */
    public function runChecks(?array $targetIds = null): array
    {
        $targets = $this->loadTargets($targetIds);
        $results = [];

        foreach ($targets as $t) {
            $typeCode = $t['TypeCode'];   // 'ssl' | 'domain' (joined from LK_TargetType)

            $result = $typeCode === 'ssl'
                ? $this->ssl->check($t['Host'], (int) $t['Port'])
                : $this->domain->check($t['Host']);

            $result['target_id'] = (int) $t['PK_MonitoredTargetID'];

            $this->persist((int) $t['PK_MonitoredTargetID'], $result);
            $results[] = $result;
        }

        return $results;
    }

    // --- internals -----------------------------------------------------------

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
