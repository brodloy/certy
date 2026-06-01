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

    /** GET /targets/create — the add form. */
    public function create(): string
    {
        require_login();

        if ($this->targetCount() >= self::MAX_TARGETS) {
            return redirect_with('/dashboard', 'error',
                'You have reached the limit of ' . self::MAX_TARGETS . ' targets.');
        }

        return view('targets/form', ['title' => 'Add target', 'target' => null], 'app');
    }

    /** POST /targets — create a target owned by this user. */
    public function store(): string
    {
        require_login();

        if ($this->targetCount() >= self::MAX_TARGETS) {
            return redirect_with('/dashboard', 'error',
                'You have reached the limit of ' . self::MAX_TARGETS . ' targets.');
        }

        $f   = $this->readInput();
        $old = $this->oldInput($f);

        $errors = $this->validate($f);
        if ($errors !== []) {
            return redirect_errors('/targets/create', $errors, $old);
        }

        $typeId = $this->typeId($f['type']);

        // Friendly duplicate check (the UNIQUE key is the real guard).
        $dupe = db()->first(
            'SELECT 1 FROM `MonitoredTarget` WHERE `FK_UserID` = ? AND `Host` = ? AND `FK_TargetTypeID` = ?',
            [current_user()['PK_UserID'], $f['host'], $typeId],
        );
        if ($dupe !== null) {
            return redirect_errors('/targets/create',
                ['host' => 'You are already monitoring that host for this check type.'], $old);
        }

        $now = gmdate('Y-m-d H:i:s');
        db()->insert('MonitoredTarget', [
            'FK_UserID'       => current_user()['PK_UserID'],
            'FK_TargetTypeID' => $typeId,
            'Host'            => $f['host'],
            'Port'            => $f['port'],
            'VerifyTls'       => $f['verifyTls'],
            'Label'           => $f['label'] !== '' ? $f['label'] : null,
            'IsActive'        => 1,
            'CreatedAt'       => $now,
            'UpdatedAt'       => $now,
        ]);

        return redirect_with('/dashboard', 'success', 'Target added. Run a check to see its status.');
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

        $f        = $this->readInput();
        $old      = $this->oldInput($f);
        $isActive = input('is_active') !== '' ? 1 : 0;

        $errors = $this->validate($f);
        if ($errors !== []) {
            return redirect_errors('/targets/' . $tid . '/edit', $errors, $old);
        }

        $typeId = $this->typeId($f['type']);

        // Duplicate check — same host+type on another of this user's targets.
        $dupe = db()->first(
            'SELECT 1 FROM `MonitoredTarget`
              WHERE `FK_UserID` = ? AND `Host` = ? AND `FK_TargetTypeID` = ? AND `PK_MonitoredTargetID` <> ?',
            [current_user()['PK_UserID'], $f['host'], $typeId, $tid],
        );
        if ($dupe !== null) {
            return redirect_errors('/targets/' . $tid . '/edit',
                ['host' => 'You are already monitoring that host for this check type.'], $old);
        }

        // If host or type changed, the old Last* snapshot no longer applies —
        // clear it so the row shows "unchecked" until the next scan.
        $changed = ($f['host'] !== $target['Host']) || ($typeId !== (int) $target['FK_TargetTypeID']);

        $sql = 'UPDATE `MonitoredTarget`
                   SET `Host` = ?, `FK_TargetTypeID` = ?, `Port` = ?, `VerifyTls` = ?, `Label` = ?, `IsActive` = ?, `UpdatedAt` = ?';
        $params = [$f['host'], $typeId, $f['port'], $f['verifyTls'], $f['label'] !== '' ? $f['label'] : null, $isActive, gmdate('Y-m-d H:i:s')];
        if ($changed) {
            $sql .= ', `LastCheckedAt` = NULL, `LastIsOk` = NULL, `LastExpiresAt` = NULL, `LastDaysLeft` = NULL';
        }
        $sql .= ' WHERE `PK_MonitoredTargetID` = ? AND `FK_UserID` = ?';
        $params[] = $tid;
        $params[] = current_user()['PK_UserID'];

        db()->run($sql, $params);

        return redirect_with('/dashboard', 'success', 'Target updated.');
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

        return redirect_with('/dashboard', 'success', 'Target deleted.');
    }

    /** GET /targets/export — all this user's targets + current status as CSV. */
    public function export(): string
    {
        require_login();

        $rows = db()->all(
            'SELECT t.*, lt.`Code` AS `TypeCode`
               FROM `MonitoredTarget` t
               JOIN `LK_TargetType` lt ON lt.`PK_TargetTypeID` = t.`FK_TargetTypeID`
              WHERE t.`FK_UserID` = ?
              ORDER BY t.`Host`',
            [current_user()['PK_UserID']],
        );

        csv_download(
            'certy-targets-' . gmdate('Ymd') . '.csv',
            ['host', 'type', 'port', 'label', 'status', 'expires_at_utc', 'days_left', 'last_checked_utc'],
            array_map(fn ($r) => [
                $r['Host'], $r['TypeCode'], $r['Port'], $r['Label'] ?? '', target_status($r),
                $r['LastExpiresAt'] ?? '', $r['LastDaysLeft'] ?? '', $r['LastCheckedAt'] ?? '',
            ], $rows),
        );
    }

    /** GET /targets/{id}/export — that target's full check history as CSV. */
    public function exportHistory(string $id): string
    {
        require_login();
        $target = $this->findOwned((int) $id);

        $rows = db()->all(
            'SELECT * FROM `CheckResult` WHERE `FK_MonitoredTargetID` = ? ORDER BY `CheckedAt` DESC',
            [$target['PK_MonitoredTargetID']],
        );

        $slug = preg_replace('/[^a-z0-9.-]/i', '_', (string) $target['Host']);
        csv_download(
            'certy-' . $slug . '-history-' . gmdate('Ymd') . '.csv',
            ['checked_at_utc', 'is_ok', 'expires_at_utc', 'days_left', 'issuer', 'subject', 'error'],
            array_map(fn ($h) => [
                $h['CheckedAt'], (int) $h['IsOk'], $h['ExpiresAt'] ?? '', $h['DaysLeft'] ?? '',
                $h['Issuer'] ?? '', $h['Subject'] ?? '', $h['ErrorText'] ?? '',
            ], $rows),
        );
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

        // No retry here: this is the interactive "Scan"/"Scan all" button, so we
        // favour a fast response over flap-smoothing (it never alerts anyway).
        (new MonitorService())->runChecks($ids, false);

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
                'status'      => target_status($r),
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

    /**
     * Read + clean the target fields shared by store() and update():
     * returns ['host', 'type', 'label', 'port'] (host normalised, port clamped).
     */
    private function readInput(): array
    {
        $port = (int) (input('port') !== '' ? input('port') : '443');
        return [
            'host'      => clean_host(input('host')),
            'type'      => input('type') === 'domain' ? 'domain' : 'ssl',
            'label'     => trim(input('label')),
            'port'      => ($port < 1 || $port > 65535) ? 443 : $port,
            'verifyTls' => input('verify_tls') !== '' ? 1 : 0,  // strict TLS (SSL only)
        ];
    }

    /** The fields to re-fill the form with after a failed submit. */
    private function oldInput(array $f): array
    {
        return [
            'host' => $f['host'], 'type' => $f['type'], 'label' => $f['label'],
            'port' => (string) $f['port'], 'verify_tls' => $f['verifyTls'] ? '1' : '',
        ];
    }

    /** Validate the cleaned fields; returns [field => message] (empty if valid). */
    private function validate(array $f): array
    {
        $errors = [];
        if ($f['host'] === '') {
            $errors['host'] = 'A host or domain is required.';
        } elseif (!$this->looksLikeHost($f['host'])) {
            $errors['host'] = 'That doesn\'t look like a valid host or domain.';
        }
        return $errors;
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
