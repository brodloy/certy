<?php
/**
 * SCAN CONTROLLER — read-only. Lists the scan results that have been generated
 * (the CheckResult rows), newest first, scoped to the current user, with
 * optional result + host filters (carried in the URL as GET params).
 *
 * It does NOT run scans. Generation happens in exactly one place — MonitorService,
 * triggered by the scan endpoint (/targets/check, all-or-one).
 */
class ScanController
{
    private const PER_PAGE = 25;

    /** GET /scans — flat list of check results, filterable by result + host. */
    public function index(): string
    {
        require_login();

        $uid  = current_user()['PK_UserID'];
        $page = max(1, (int) input('page', '1'));

        // Filters
        $fResult = in_array(input('result'), ['ok', 'failed'], true) ? input('result') : '';
        $fHost   = trim(input('host'));

        // Build a shared WHERE clause + params from the active filters.
        $where  = ['t.`FK_UserID` = ?'];
        $params = [$uid];
        if ($fResult === 'ok') {
            $where[] = 'cr.`IsOk` = 1';
        } elseif ($fResult === 'failed') {
            $where[] = 'cr.`IsOk` = 0';
        }
        if ($fHost !== '') {
            $where[] = 't.`Host` = ?';
            $params[] = $fHost;
        }
        $whereSql = implode(' AND ', $where);

        $countRow = db()->first(
            "SELECT COUNT(*) AS c
               FROM `CheckResult` cr
               JOIN `MonitoredTarget` t ON t.`PK_MonitoredTargetID` = cr.`FK_MonitoredTargetID`
              WHERE {$whereSql}",
            $params,
        );
        $total      = (int) ($countRow['c'] ?? 0);
        $totalPages = (int) ceil($total / self::PER_PAGE);
        $offset     = (max(1, $page) - 1) * self::PER_PAGE;
        $limit      = self::PER_PAGE;

        $rows = db()->all(
            "SELECT cr.*, t.`Host`, t.`Label`, lt.`Code` AS `TypeCode`, lt.`Label` AS `TypeLabel`
               FROM `CheckResult` cr
               JOIN `MonitoredTarget` t  ON t.`PK_MonitoredTargetID` = cr.`FK_MonitoredTargetID`
               JOIN `LK_TargetType`  lt ON lt.`PK_TargetTypeID` = t.`FK_TargetTypeID`
              WHERE {$whereSql}
              ORDER BY cr.`CheckedAt` DESC, cr.`PK_CheckResultID` DESC
              LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        // Distinct hosts for the filter dropdown (this user's targets).
        $hostRows = db()->all(
            'SELECT DISTINCT `Host` FROM `MonitoredTarget` WHERE `FK_UserID` = ? ORDER BY `Host`',
            [$uid],
        );
        $hosts = array_map(fn ($h) => $h['Host'], $hostRows);

        return view('scans/index', [
            'title'   => 'Scans',
            'rows'    => $rows,
            'total'   => $total,
            'meta'    => ['page' => $page, 'perPage' => self::PER_PAGE, 'total' => $total, 'totalPages' => $totalPages],
            'hosts'   => $hosts,
            'fResult' => $fResult,
            'fHost'   => $fHost,
        ], 'app');
    }
}
