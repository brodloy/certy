<?php
/**
 * ADMIN — an example admin-only area. require_admin() at the top of each method
 * is the whole gate: guests go to /login, signed-in non-admins get a 403.
 * (The demo seed includes admin@example.com / password.)
 */
class AdminController
{
    /**
     * GET /admin — operational overview for admins: who's signed up, how many
     * targets exist, and whether the scanner (manual + scheduled) is running.
     * Read-only and system-wide — not scoped to the admin's own targets.
     */
    public function index(): string
    {
        require_admin();

        // --- Users ---------------------------------------------------------
        $users = db()->first(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN `VerifiedAt` IS NOT NULL THEN 1 ELSE 0 END) AS verified,
                    SUM(CASE WHEN `Role` = 'admin' THEN 1 ELSE 0 END) AS admins
               FROM `User`",
        );
        $cutoff7   = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
        $newUsers7 = (int) (db()->first(
            "SELECT COUNT(*) AS c FROM `User` WHERE `CreatedAt` >= ?", [$cutoff7],
        )['c'] ?? 0);

        // --- Targets -------------------------------------------------------
        $targets = db()->first(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN `IsActive` = 1 THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN `IsActive` = 0 THEN 1 ELSE 0 END) AS paused
               FROM `MonitoredTarget`",
        );
        $byType = db()->all(
            "SELECT lt.`Code` AS code, lt.`Label` AS label, COUNT(*) AS c
               FROM `MonitoredTarget` t
               JOIN `LK_TargetType` lt ON lt.`PK_TargetTypeID` = t.`FK_TargetTypeID`
              GROUP BY lt.`Code`, lt.`Label`
              ORDER BY c DESC",
        );

        // System-wide status tally — derived in PHP (same rule as everywhere).
        $health = ['healthy' => 0, 'warning' => 0, 'critical' => 0, 'expired' => 0, 'failed' => 0, 'unknown' => 0];
        foreach (db()->all("SELECT `LastIsOk`, `LastDaysLeft` FROM `MonitoredTarget`") as $s) {
            $health[target_status($s)]++;
        }

        // --- Checks + scan runs -------------------------------------------
        $checks = db()->first("SELECT COUNT(*) AS total, MAX(`CheckedAt`) AS last FROM `CheckResult`");

        $runStats = db()->first(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN `Mode` = 'due'  THEN 1 ELSE 0 END) AS due_runs,
                    SUM(CASE WHEN `Mode` = 'full' THEN 1 ELSE 0 END) AS full_runs
               FROM `MonitorRun`",
        );
        $lastDue    = db()->first("SELECT * FROM `MonitorRun` WHERE `Mode` = 'due'  ORDER BY `PK_MonitorRunID` DESC LIMIT 1");
        $lastFull   = db()->first("SELECT * FROM `MonitorRun` WHERE `Mode` = 'full' ORDER BY `PK_MonitorRunID` DESC LIMIT 1");
        $recentRuns = db()->all("SELECT * FROM `MonitorRun` ORDER BY `PK_MonitorRunID` DESC LIMIT 10");

        return view('admin/index', [
            'title'      => 'Admin',
            'users'      => $users,
            'newUsers7'  => $newUsers7,
            'targets'    => $targets,
            'byType'     => $byType,
            'health'     => $health,
            'checks'     => $checks,
            'runStats'   => $runStats,
            'lastDue'    => $lastDue,
            'lastFull'   => $lastFull,
            'recentRuns' => $recentRuns,
        ], 'app');
    }

    /** GET /admin/export — the MonitorRun log as CSV. */
    public function exportRuns(): string
    {
        require_admin();

        $rows = db()->all('SELECT * FROM `MonitorRun` ORDER BY `PK_MonitorRunID` DESC');

        csv_download(
            'certy-monitor-runs-' . gmdate('Ymd') . '.csv',
            ['started_at_utc', 'mode', 'due_count', 'checked', 'ok', 'failed', 'duration_ms'],
            array_map(fn ($r) => [
                $r['StartedAt'], $r['Mode'], $r['DueCount'] ?? '',
                $r['CheckedCount'], $r['OkCount'], $r['FailedCount'], $r['DurationMs'],
            ], $rows),
        );
    }

    public function users(): string
    {
        require_admin();

        $page = max(1, (int) input('page', '1'));
        $result = db()->paginate('User', '', [], $page, 15, 'ORDER BY `PK_UserID` ASC');

        return view('admin/users', [
            'title'  => 'Users',
            'result' => $result,
        ], 'app');
    }

    /**
     * POST /admin/users/{id}/toggle — enable/disable a user's account. A
     * disabled account is refused at login and bounced mid-session (Auth
     * re-checks `Active` every request), and its remember-me tokens are dropped.
     * Admins can't disable themselves (that would lock them out instantly).
     */
    public function toggleActive(string $id): string
    {
        require_admin();
        $uid = (int) $id;

        if ($uid === (int) current_user()['PK_UserID']) {
            return redirect_with('/admin/users', 'error', "You can't disable your own account.");
        }

        $user = db()->first('SELECT `PK_UserID`, `Name`, `Active` FROM `User` WHERE `PK_UserID` = ?', [$uid]);
        if ($user === null) {
            return redirect_with('/admin/users', 'error', 'User not found.');
        }

        $next = (int) $user['Active'] === 1 ? 0 : 1;
        db()->run(
            'UPDATE `User` SET `Active` = ?, `UpdatedAt` = ? WHERE `PK_UserID` = ?',
            [$next, gmdate('Y-m-d H:i:s'), $uid],
        );

        if ($next === 0) {
            // Kill any remember-me cookies so the disable takes effect immediately.
            db()->run('DELETE FROM `RememberToken` WHERE `FK_UserID` = ?', [$uid]);
        }

        return redirect_with('/admin/users', 'success',
            $user['Name'] . ' ' . ($next === 1 ? 'enabled' : 'disabled') . '.');
    }
}
