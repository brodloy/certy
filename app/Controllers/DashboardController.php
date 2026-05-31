<?php
/**
 * DASHBOARD — the first page after signing in. Reads the user's targets with
 * their denormalised Last* snapshot (fast: one query, no join to history) and
 * lets the view colour-code each by derived status. Supports result + host
 * filters on the *table* — the KPI cards always reflect ALL targets, so the
 * filter narrows the list without distorting the summary counts.
 */
class DashboardController
{
    public function index(): string
    {
        require_login(); // guests get bounced to /login

        $user = current_user();
        $uid  = $user['PK_UserID'];

        $fResult = in_array(input('result'), ['ok', 'failed'], true) ? input('result') : '';
        $fHost   = trim(input('host'));

        // Full set (for KPI tally + the host dropdown), newest-urgency first.
        $all = db()->all(
            'SELECT t.*, lt.`Code` AS `TypeCode`, lt.`Label` AS `TypeLabel`
               FROM `MonitoredTarget` t
               JOIN `LK_TargetType` lt ON lt.`PK_TargetTypeID` = t.`FK_TargetTypeID`
              WHERE t.`FK_UserID` = ?
              ORDER BY (t.`LastIsOk` IS NULL) ASC,
                       (t.`LastDaysLeft` IS NULL) ASC,
                       t.`LastDaysLeft` ASC',
            [$uid],
        );

        // KPI tally from ALL targets (never filtered).
        $tally = ['healthy' => 0, 'warning' => 0, 'critical' => 0, 'expired' => 0, 'failed' => 0, 'unknown' => 0];
        $hosts = [];
        foreach ($all as $r) {
            $tally[monitor_status(
                $r['LastIsOk'] === null ? null : (int) $r['LastIsOk'],
                $r['LastDaysLeft'] === null ? null : (int) $r['LastDaysLeft'],
            )]++;
            $hosts[$r['Host']] = true;
        }

        // Apply filters to the rows shown in the table.
        $rows = array_values(array_filter($all, function ($r) use ($fResult, $fHost) {
            if ($fHost !== '' && $r['Host'] !== $fHost) {
                return false;
            }
            if ($fResult !== '') {
                $status = monitor_status(
                    $r['LastIsOk'] === null ? null : (int) $r['LastIsOk'],
                    $r['LastDaysLeft'] === null ? null : (int) $r['LastDaysLeft'],
                );
                // 'ok' = healthy/warning/critical (a successful check); 'failed' = check failed (unknown via failure)
                if ($fResult === 'ok'     && ($r['LastIsOk'] === null || (int) $r['LastIsOk'] !== 1)) {
                    return false;
                }
                if ($fResult === 'failed' && (int) ($r['LastIsOk'] ?? -1) !== 0) {
                    return false;
                }
            }
            return true;
        }));

        // Order the visible rows by severity (worst first) rather than raw days,
        // so problems surface at the top: expired, then failed (can't reach the
        // host), then critical, warning, healthy, and never-checked last. Within
        // a tier, fewest days left first. Ranking reuses monitor_status() so it
        // always tracks the status thresholds.
        $rank = ['expired' => 0, 'failed' => 1, 'critical' => 2, 'warning' => 3, 'healthy' => 4, 'unknown' => 5];
        usort($rows, function ($a, $b) use ($rank) {
            $statusOf = fn ($r) => monitor_status(
                $r['LastIsOk'] === null ? null : (int) $r['LastIsOk'],
                $r['LastDaysLeft'] === null ? null : (int) $r['LastDaysLeft'],
            );
            $cmp = $rank[$statusOf($a)] <=> $rank[$statusOf($b)];
            if ($cmp !== 0) {
                return $cmp;
            }
            $da = $a['LastDaysLeft'] === null ? PHP_INT_MAX : (int) $a['LastDaysLeft'];
            $db = $b['LastDaysLeft'] === null ? PHP_INT_MAX : (int) $b['LastDaysLeft'];
            return $da <=> $db;
        });

        return view('dashboard', [
            'title'   => 'Dashboard',
            'user'    => $user,
            'rows'    => $rows,
            'tally'   => $tally,
            'count'   => count($all),
            'max'     => 10,
            'hosts'   => array_keys($hosts),
            'fResult' => $fResult,
            'fHost'   => $fHost,
        ], 'app');
    }
}
