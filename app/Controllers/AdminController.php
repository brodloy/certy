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

        // --- Operational health -------------------------------------------
        // The systemd timer runs `monitor:run --due` hourly, so a scheduled run
        // row should appear ~hourly. A much older newest run => scheduler down.
        $staleAfterMin = 90;
        $minsAgo = $lastDue === null
            ? null
            : max(0, (int) floor((time() - strtotime((string) $lastDue['StartedAt'])) / 60));
        $scheduler = [
            'lastAt'        => $lastDue['StartedAt'] ?? null,
            'minsAgo'       => $minsAgo,
            'healthy'       => $minsAgo !== null && $minsAgo <= $staleAfterMin,
            'staleAfterMin' => $staleAfterMin,
        ];

        // Targets whose last check errored (failing right now).
        $failingNow = (int) (db()->first(
            "SELECT COUNT(*) AS c FROM `MonitoredTarget` WHERE `LastIsOk` = 0")['c'] ?? 0);

        // Worker-queue depth (only meaningful if the scale-out queue is used).
        $queue = db()->first(
            "SELECT COALESCE(SUM(`Status` = 'pending'), 0) AS pending,
                    COALESCE(SUM(`Status` = 'running'), 0) AS running,
                    COALESCE(SUM(`Status` = 'failed'),  0) AS failed
               FROM `ScanJob`",
        );

        // Scan activity split by source (scheduled vs user-triggered).
        $activity = [
            '24h' => $this->scanActivity(gmdate('Y-m-d H:i:s', time() - 86400)),
            '7d'  => $this->scanActivity($cutoff7),
        ];

        // Most recent failures — the "what's broken right now" list.
        $recentFailures = db()->all(
            "SELECT cr.`CheckedAt`, cr.`Source`, cr.`ErrorText`, cr.`FK_MonitorRunID`,
                    t.`Host`, t.`Label`
               FROM `CheckResult` cr
               JOIN `MonitoredTarget` t ON t.`PK_MonitoredTargetID` = cr.`FK_MonitoredTargetID`
              WHERE cr.`IsOk` = 0
              ORDER BY cr.`CheckedAt` DESC, cr.`PK_CheckResultID` DESC LIMIT 12",
        );

        return view('admin/index', [
            'title'          => 'Admin',
            'users'          => $users,
            'newUsers7'      => $newUsers7,
            'targets'        => $targets,
            'byType'         => $byType,
            'health'         => $health,
            'checks'         => $checks,
            'runStats'       => $runStats,
            'lastDue'        => $lastDue,
            'lastFull'       => $lastFull,
            'recentRuns'     => $recentRuns,
            'scheduler'      => $scheduler,
            'failingNow'     => $failingNow,
            'queue'          => $queue,
            'activity'       => $activity,
            'recentFailures' => $recentFailures,
        ], 'app');
    }

    /** Per-source scan tallies since $cutoff: ['scheduled'=>[total,ok,failed], 'manual'=>…]. */
    private function scanActivity(string $cutoff): array
    {
        $out = [
            'scheduled' => ['total' => 0, 'ok' => 0, 'failed' => 0],
            'manual'    => ['total' => 0, 'ok' => 0, 'failed' => 0],
        ];
        $rows = db()->all(
            "SELECT `Source`, COUNT(*) AS total,
                    COALESCE(SUM(`IsOk` = 1), 0) AS ok, COALESCE(SUM(`IsOk` = 0), 0) AS failed
               FROM `CheckResult` WHERE `CheckedAt` >= ? GROUP BY `Source`",
            [$cutoff],
        );
        foreach ($rows as $r) {
            $src = $r['Source'] === 'manual' ? 'manual' : 'scheduled';
            $out[$src] = ['total' => (int) $r['total'], 'ok' => (int) $r['ok'], 'failed' => (int) $r['failed']];
        }
        return $out;
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

    /** GET /admin/runs — the full scheduled-run log, paginated. */
    public function runs(): string
    {
        require_admin();
        $page   = max(1, (int) input('page', '1'));
        $result = db()->paginate('MonitorRun', '', [], $page, 25, 'ORDER BY `PK_MonitorRunID` DESC');

        return view('admin/runs', ['title' => 'Scan runs', 'result' => $result], 'app');
    }

    /** GET /admin/runs/{id} — one run plus exactly the checks it produced. */
    public function runDetail(string $id): string
    {
        require_admin();
        $run = db()->first('SELECT * FROM `MonitorRun` WHERE `PK_MonitorRunID` = ?', [(int) $id]);
        if ($run === null) {
            abort(404, 'Run not found.');
        }

        $checks = db()->all(
            "SELECT cr.*, t.`Host`, t.`Label`
               FROM `CheckResult` cr
               JOIN `MonitoredTarget` t ON t.`PK_MonitoredTargetID` = cr.`FK_MonitoredTargetID`
              WHERE cr.`FK_MonitorRunID` = ?
              ORDER BY cr.`IsOk` ASC, t.`Host`",
            [(int) $id],
        );

        return view('admin/run', ['title' => 'Run #' . (int) $id, 'run' => $run, 'checks' => $checks], 'app');
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
