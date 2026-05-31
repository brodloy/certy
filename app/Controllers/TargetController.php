<?php
/**
 * TARGET CONTROLLER — the user's monitored hosts/domains. Mirrors the
 * the standard pattern: require_login() on every method, and every query
 * scoped to the current user (WHERE FK_UserID = ?) so nobody can see or touch
 * anyone else's targets, even by guessing ids.
 *
 * The "Check Now" endpoints call MonitorService (which runs + persists) and
 * return JSON. They never alert — the user is looking at the screen.
 */
class TargetController
{
    private const MAX_TARGETS = 10;

    /** GET /targets — this user's targets, newest first, filterable. */
    public function index(): string
    {
        require_login();
        $uid = current_user()['PK_UserID'];

        $fResult = in_array(input('result'), ['ok', 'failed'], true) ? input('result') : '';
        $fHost   = trim(input('host'));

        $all = db()->all(
            'SELECT t.*, lt.`Code` AS `TypeCode`, lt.`Label` AS `TypeLabel`
               FROM `MonitoredTarget` t
               JOIN `LK_TargetType` lt ON lt.`PK_TargetTypeID` = t.`FK_TargetTypeID`
              WHERE t.`FK_UserID` = ?
              ORDER BY t.`CreatedAt` DESC',
            [$uid],
        );

        $hosts = [];
        foreach ($all as $r) {
            $hosts[$r['Host']] = true;
        }

        $rows = array_values(array_filter($all, function ($r) use ($fResult, $fHost) {
            if ($fHost !== '' && $r['Host'] !== $fHost) {
                return false;
            }
            if ($fResult === 'ok'     && ($r['LastIsOk'] === null || (int) $r['LastIsOk'] !== 1)) {
                return false;
            }
            if ($fResult === 'failed' && (int) ($r['LastIsOk'] ?? -1) !== 0) {
                return false;
            }
            return true;
        }));

        return view('targets/index', [
            'title'   => 'Targets',
            'rows'    => $rows,
            'count'   => count($all),
            'max'     => self::MAX_TARGETS,
            'hosts'   => array_keys($hosts),
            'fResult' => $fResult,
            'fHost'   => $fHost,
        ], 'app');
    }

    /** GET /targets/create — the add form. */
    public function create(): string
    {
        require_login();

        if ($this->targetCount() >= self::MAX_TARGETS) {
            return redirect_with('/targets', 'error',
                'You have reached the limit of ' . self::MAX_TARGETS . ' targets.');
        }

        return view('targets/form', ['title' => 'Add target', 'target' => null], 'app');
    }

    /** POST /targets — create a target owned by this user. */
    public function store(): string
    {
        require_login();

        if ($this->targetCount() >= self::MAX_TARGETS) {
            return redirect_with('/targets', 'error',
                'You have reached the limit of ' . self::MAX_TARGETS . ' targets.');
        }

        $host  = $this->cleanHost(input('host'));
        $type  = input('type') === 'domain' ? 'domain' : 'ssl';
        $label = trim(input('label'));
        $port  = (int) (input('port') !== '' ? input('port') : '443');
        if ($port < 1 || $port > 65535) {
            $port = 443;
        }

        $errors = [];
        if ($host === '') {
            $errors['host'] = 'A host or domain is required.';
        } elseif (!$this->looksLikeHost($host)) {
            $errors['host'] = 'That doesn\'t look like a valid host or domain.';
        }
        if ($errors !== []) {
            return redirect_errors('/targets/create', $errors,
                ['host' => $host, 'type' => $type, 'label' => $label, 'port' => (string) $port]);
        }

        $typeId = $this->typeId($type);

        // Friendly duplicate check (the UNIQUE key is the real guard).
        $dupe = db()->first(
            'SELECT 1 FROM `MonitoredTarget` WHERE `FK_UserID` = ? AND `Host` = ? AND `FK_TargetTypeID` = ?',
            [current_user()['PK_UserID'], $host, $typeId],
        );
        if ($dupe !== null) {
            return redirect_errors('/targets/create',
                ['host' => 'You are already monitoring that host for this check type.'],
                ['host' => $host, 'type' => $type, 'label' => $label, 'port' => (string) $port]);
        }

        $now = gmdate('Y-m-d H:i:s');
        db()->insert('MonitoredTarget', [
            'FK_UserID'       => current_user()['PK_UserID'],
            'FK_TargetTypeID' => $typeId,
            'Host'            => $host,
            'Port'            => $port,
            'Label'           => $label !== '' ? $label : null,
            'IsActive'        => 1,
            'CreatedAt'       => $now,
            'UpdatedAt'       => $now,
        ]);

        return redirect_with('/targets', 'success', 'Target added. Run a check to see its status.');
    }

    /** GET /targets/{id}/edit — the edit form for an owned target. */
    public function edit(string $id): string
    {
        require_login();
        $target = $this->findOwned((int) $id);

        return view('targets/form', [
            'title'  => 'Edit target',
            'target' => $target,
        ], 'app');
    }

    /** POST /targets/{id} — update an owned target. */
    public function update(string $id): string
    {
        require_login();
        $target = $this->findOwned((int) $id);
        $tid    = (int) $target['PK_MonitoredTargetID'];

        $host  = $this->cleanHost(input('host'));
        $type  = input('type') === 'domain' ? 'domain' : 'ssl';
        $label = trim(input('label'));
        $port  = (int) (input('port') !== '' ? input('port') : '443');
        if ($port < 1 || $port > 65535) {
            $port = 443;
        }
        $isActive = input('is_active') !== '' ? 1 : 0;

        $errors = [];
        if ($host === '') {
            $errors['host'] = 'A host or domain is required.';
        } elseif (!$this->looksLikeHost($host)) {
            $errors['host'] = 'That doesn\'t look like a valid host or domain.';
        }
        if ($errors !== []) {
            return redirect_errors('/targets/' . $tid . '/edit', $errors,
                ['host' => $host, 'type' => $type, 'label' => $label, 'port' => (string) $port]);
        }

        $typeId = $this->typeId($type);

        // Duplicate check — same host+type on another of this user's targets.
        $dupe = db()->first(
            'SELECT 1 FROM `MonitoredTarget`
              WHERE `FK_UserID` = ? AND `Host` = ? AND `FK_TargetTypeID` = ? AND `PK_MonitoredTargetID` <> ?',
            [current_user()['PK_UserID'], $host, $typeId, $tid],
        );
        if ($dupe !== null) {
            return redirect_errors('/targets/' . $tid . '/edit',
                ['host' => 'You are already monitoring that host for this check type.'],
                ['host' => $host, 'type' => $type, 'label' => $label, 'port' => (string) $port]);
        }

        // If host or type changed, the old Last* snapshot no longer applies —
        // clear it so the row shows "unchecked" until the next scan.
        $changed = ($host !== $target['Host']) || ($typeId !== (int) $target['FK_TargetTypeID']);

        $sql = 'UPDATE `MonitoredTarget`
                   SET `Host` = ?, `FK_TargetTypeID` = ?, `Port` = ?, `Label` = ?, `IsActive` = ?, `UpdatedAt` = ?';
        $params = [$host, $typeId, $port, $label !== '' ? $label : null, $isActive, gmdate('Y-m-d H:i:s')];
        if ($changed) {
            $sql .= ', `LastCheckedAt` = NULL, `LastIsOk` = NULL, `LastExpiresAt` = NULL, `LastDaysLeft` = NULL';
        }
        $sql .= ' WHERE `PK_MonitoredTargetID` = ? AND `FK_UserID` = ?';
        $params[] = $tid;
        $params[] = current_user()['PK_UserID'];

        db()->run($sql, $params);

        return redirect_with('/targets', 'success', 'Target updated.');
    }

    /** GET /targets/{id} — per-target history view (ownership checked). */
    public function show(string $id): string
    {
        require_login();
        $target = $this->findOwned((int) $id);

        $history = db()->all(
            'SELECT * FROM `CheckResult` WHERE `FK_MonitoredTargetID` = ?
              ORDER BY `CheckedAt` DESC LIMIT 50',
            [$target['PK_MonitoredTargetID']],
        );

        return view('targets/show', [
            'title'   => $target['Label'] ?: $target['Host'],
            'target'  => $target,
            'history' => $history,
        ], 'app');
    }

    /** POST /targets/{id}/delete — delete (ownership checked; history cascades). */
    public function destroy(string $id): string
    {
        require_login();
        $target = $this->findOwned((int) $id);

        db()->run(
            'DELETE FROM `MonitoredTarget` WHERE `PK_MonitoredTargetID` = ? AND `FK_UserID` = ?',
            [$target['PK_MonitoredTargetID'], current_user()['PK_UserID']],
        );

        return redirect_with('/targets', 'success', 'Target deleted.');
    }

    /**
     * POST /targets/check — run checks on demand and return JSON. With ?id=N
     * checks one target; without, checks all of this user's targets. Never
     * alerts (the user is watching the screen). Ownership-scoped: a user can
     * only ever trigger checks on their own targets.
     */
    public function check(): string
    {
        require_login();

        $ids = $this->ownedIdsFromRequest();
        if ($ids === []) {
            return json(['ok' => false, 'error' => 'No matching targets.'], 404);
        }

        (new MonitorService())->runChecks($ids);

        // Return the refreshed snapshot rows so the page can update in place.
        $holders = implode(', ', array_fill(0, count($ids), '?'));
        $rows = db()->all(
            "SELECT `PK_MonitoredTargetID`, `LastCheckedAt`, `LastIsOk`,
                    `LastExpiresAt`, `LastDaysLeft`
               FROM `MonitoredTarget`
              WHERE `FK_UserID` = ? AND `PK_MonitoredTargetID` IN ({$holders})",
            array_merge([current_user()['PK_UserID']], $ids),
        );

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'          => (int) $r['PK_MonitoredTargetID'],
                'status'      => monitor_status(
                    $r['LastIsOk'] === null ? null : (int) $r['LastIsOk'],
                    $r['LastDaysLeft'] === null ? null : (int) $r['LastDaysLeft'],
                ),
                'days_left'   => $r['LastDaysLeft'] === null ? null : (int) $r['LastDaysLeft'],
                'expires_at'  => $r['LastExpiresAt'],
                'checked_at'  => $r['LastCheckedAt'],
            ];
        }

        return json(['ok' => true, 'results' => $out]);
    }

    // --- helpers -------------------------------------------------------------

    /** Resolve ?id=N to a list of owned ids, or all of the user's ids if absent. */
    private function ownedIdsFromRequest(): array
    {
        $uid = current_user()['PK_UserID'];

        if (input('id') !== '') {
            $row = db()->first(
                'SELECT `PK_MonitoredTargetID` FROM `MonitoredTarget`
                  WHERE `PK_MonitoredTargetID` = ? AND `FK_UserID` = ?',
                [(int) input('id'), $uid],
            );
            return $row !== null ? [(int) $row['PK_MonitoredTargetID']] : [];
        }

        $rows = db()->all(
            'SELECT `PK_MonitoredTargetID` FROM `MonitoredTarget` WHERE `FK_UserID` = ? AND `IsActive` = 1',
            [$uid],
        );
        return array_map(fn ($r) => (int) $r['PK_MonitoredTargetID'], $rows);
    }

    private function targetCount(): int
    {
        $row = db()->first(
            'SELECT COUNT(*) AS c FROM `MonitoredTarget` WHERE `FK_UserID` = ?',
            [current_user()['PK_UserID']],
        );
        return (int) ($row['c'] ?? 0);
    }

    private function typeId(string $code): int
    {
        $row = db()->first('SELECT `PK_TargetTypeID` FROM `LK_TargetType` WHERE `Code` = ?', [$code]);
        return (int) ($row['PK_TargetTypeID'] ?? 1);
    }

    /** Strip scheme/path/www and lower-case, so pasted URLs become bare hosts. */
    private function cleanHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }
        if (str_contains($host, '://')) {
            $host = (string) parse_url($host, PHP_URL_HOST);
        }
        $host = preg_replace('#[/:].*$#', '', (string) $host);
        $host = preg_replace('#^www\.#', '', (string) $host);
        return (string) $host;
    }

    private function looksLikeHost(string $host): bool
    {
        // A dot, sane characters, sane length — not a full RFC validation.
        return (bool) preg_match('/^(?=.{1,253}$)([a-z0-9-]+\.)+[a-z]{2,}$/', $host);
    }

    /** Find a target by id that belongs to the current user, or 404. */
    private function findOwned(int $id): array
    {
        $target = db()->first(
            'SELECT t.*, lt.`Code` AS `TypeCode`, lt.`Label` AS `TypeLabel`
               FROM `MonitoredTarget` t
               JOIN `LK_TargetType` lt ON lt.`PK_TargetTypeID` = t.`FK_TargetTypeID`
              WHERE t.`PK_MonitoredTargetID` = ? AND t.`FK_UserID` = ?',
            [$id, current_user()['PK_UserID']],
        );

        if ($target === null) {
            http_response_code(404);
            exit(view('errors/404', ['title' => 'Not found']));
        }

        return $target;
    }
}
