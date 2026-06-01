# certy — Database

> **Keep this current.** Update in the same task as any migration or query change.
> Last verified against the migrations: 2026-05-31.

## Conventions

- **Engine/charset:** InnoDB, utf8mb4 / utf8mb4_unicode_ci.
- **Table names:** PascalCase (`User`, `MonitoredTarget`). Lookup tables prefixed
  `LK_`.
- **Keys:** primary key `PK_<Entity>ID`, foreign key `FK_<Entity>ID`.
- **Timestamps:** `CreatedAt` / `UpdatedAt` / `*At` columns are `DATETIME`, stored
  in **UTC** (set in PHP with `gmdate('Y-m-d H:i:s')`). Displayed in the user's
  timezone via `format_date()`.
- **Foreign keys** cascade on delete (`ON DELETE CASCADE`) so deleting a user or
  target cleans up its children automatically.
- **Access:** always through `db()` with bound parameters — never string-built SQL.

## ⚠ Table-name casing caveat (important)

MySQL on **Windows** runs with `lower_case_table_names=1`: it stores table names
lowercase and compares case-insensitively. So `CREATE TABLE \`User\`` becomes
`user` on disk, but querying `\`User\`` still works. On **Linux** the default is
case-sensitive, so the same code would break. The project is left PascalCase
intentionally; **before any Linux deploy**, lowercase the table names in the
migrations and queries (a deliberate, separate task).

## certy tables

### Lookup tables (seeded in their migrations)

**`LK_TargetType`** — kinds of thing we monitor.
| Column | Type | Notes |
|---|---|---|
| `PK_TargetTypeID` | TINYINT UNSIGNED PK | |
| `Code` | VARCHAR(20) UNIQUE | `ssl`, `domain` |
| `Label` | VARCHAR(50) | display name |
Seeded: `1=ssl`, `2=domain`.

**`LK_AlertType`** — kind of alert (used by `AlertDispatcher`).
Seeded: `1=expiry`, `2=check_failure`.

**`LK_AlertThreshold`** — day-tiers for expiry warnings (used by `AlertDispatcher`).
| Column | Type | Notes |
|---|---|---|
| `PK_AlertThresholdID` | TINYINT UNSIGNED PK | |
| `Days` | SMALLINT UNSIGNED UNIQUE | 30 / 14 / 7 / 1 |
| `Label` | VARCHAR(30) | |
| `IsActive` | TINYINT(1) DEFAULT 1 | disable a tier without deleting |

### `MonitoredTarget` — one row per thing a user watches

| Column | Type | Notes |
|---|---|---|
| `PK_MonitoredTargetID` | INT UNSIGNED PK AI | |
| `FK_UserID` | INT UNSIGNED | → `User`, cascade. **The ownership boundary.** |
| `FK_TargetTypeID` | TINYINT UNSIGNED | → `LK_TargetType` |
| `Host` | VARCHAR(255) | bare hostname/domain |
| `Port` | SMALLINT UNSIGNED DEFAULT 443 | SSL only |
| `VerifyTls` | TINYINT(1) DEFAULT 0 | SSL only. 1 = strict: the cert must also pass chain + hostname verification or the check fails (not just expiry). |
| `Label` | VARCHAR(255) NULL | friendly name |
| `IsActive` | TINYINT(1) DEFAULT 1 | paused targets aren't scanned by "scan all" |
| `LastCheckedAt` / `LastIsOk` / `LastExpiresAt` / `LastDaysLeft` | (nullable) | **Denormalised snapshot of the latest check** — the dashboard reads only these, so it never joins history. `LastDaysLeft` is signed (negative = expired). Cleared on host/type change. |
| `CreatedAt` / `UpdatedAt` | DATETIME | UTC |

Keys: `UNIQUE(FK_UserID, Host, FK_TargetTypeID)` (no dupes per user); index on
`FK_UserID`. The 10-target cap is enforced in the controller, not the schema.

### `CheckResult` — full scan history (one row per check run)

| Column | Type | Notes |
|---|---|---|
| `PK_CheckResultID` | INT UNSIGNED PK AI | |
| `FK_MonitoredTargetID` | INT UNSIGNED | → `MonitoredTarget`, cascade |
| `IsOk` | TINYINT(1) | did the check itself succeed |
| `ExpiresAt` / `DaysLeft` | (nullable) | null on failure |
| `Issuer` / `Subject` | VARCHAR(255) NULL | SSL only |
| `ErrorText` | VARCHAR(500) NULL | populated on failure |
| `CheckedAt` | DATETIME | UTC |

Index `(FK_MonitoredTargetID, CheckedAt)` serves both "latest per target" and the
history timeline. **No stored status** — urgency is derived at render time (see
`monitor_status()` in `conventions`), so it can never go stale.

### `AlertLog` — dedup ledger for sent alerts

Written + read by `AlertDispatcher` (from `monitor:run`). A row records that an
alert was sent for a target. Expiry alerts key on
`(target, type=expiry, threshold, ExpiresAtSnapshot)` — `alreadySent()` checks
that tuple, so each tier fires once per certificate cycle and re-arms when the
expiry moves forward (a renewal). Failure alerts store null threshold + null
snapshot; they're deduped by the ok→failed transition, not this table.

| Column | Type | Notes |
|---|---|---|
| `PK_AlertLogID` | INT UNSIGNED PK AI | |
| `FK_MonitoredTargetID` | INT UNSIGNED | → `MonitoredTarget`, cascade |
| `FK_AlertTypeID` | TINYINT UNSIGNED | → `LK_AlertType` |
| `FK_AlertThresholdID` | TINYINT UNSIGNED NULL | → `LK_AlertThreshold`; null for failure alerts |
| `ExpiresAtSnapshot` | DATETIME NULL | the expiry an alert fired against — a changed value = new cycle = alerts fire fresh after renewal |
| `SentAt` | DATETIME | UTC |

### `MonitorRun` — one row per scheduled/CLI scan run

Operational log so you can confirm the scheduler is firing and see what each run
did. Written only by `console monitor:run` (see `scheduling.md`); read by the
**admin dashboard** (`/admin`) for the last scheduled/manual run + recent history.
Not tied to a target — no foreign key, pure append-only.

| Column | Type | Notes |
|---|---|---|
| `PK_MonitorRunID` | INT UNSIGNED PK AI | |
| `StartedAt` | DATETIME | UTC, when the run began |
| `Mode` | VARCHAR(10) | `due` (interval-filtered) or `full` (all active) |
| `DueCount` | SMALLINT UNSIGNED NULL | targets selected as due in `--due` mode; **NULL** for a `full` run |
| `CheckedCount` / `OkCount` / `FailedCount` | SMALLINT UNSIGNED | targets actually checked, and the ok/fail split |
| `DurationMs` | INT UNSIGNED | wall-clock of the run |

A "nothing due" run is still recorded (`CheckedCount = 0`) — its presence is the
proof the run happened. Index on `StartedAt` for "recent runs" reads.

### `ScanJob` — work queue for the scalable scanner

A DB-backed queue that decouples scan **discovery** from **execution** so the
scanner scales out (see `scheduling.md`). `monitor:enqueue` inserts due targets;
many `monitor:work` processes claim batches concurrently and run them.

| Column | Type | Notes |
|---|---|---|
| `PK_ScanJobID` | INT UNSIGNED PK AI | |
| `FK_MonitoredTargetID` | INT UNSIGNED | → `MonitoredTarget`, cascade |
| `Status` | VARCHAR(10) | `pending` / `running` / `failed` |
| `ClaimedBy` | VARCHAR(40) NULL | the worker token that owns a `running` row |
| `ClaimedAt` | DATETIME NULL | when claimed (stale `running` rows are reclaimable) |
| `Attempts` | TINYINT UNSIGNED | retry counter; ≥5 parks the job as `failed` |
| `CreatedAt` | DATETIME | UTC |

Concurrency is safe **without `SKIP LOCKED`** (works on MySQL 5.7+): a worker
stamps its unique `ClaimedBy` token via `UPDATE … ORDER BY … LIMIT`, then SELECTs
the rows carrying its token — InnoDB row locks serialise the UPDATE so no row is
claimed twice. Completed jobs are **DELETED** (this is a queue, not a log —
`CheckResult`/`MonitorRun` are the history). `db:cleanup` prunes parked `failed`
jobs.

## Starter tables (inherited, not certy-specific)

`User`, `PasswordReset`, `LoginAttempt`, `EmailVerification`, `RememberToken`,
`OAuthIdentity` (generic `Provider` + `ProviderUserID` — provider-agnostic),
plus two **unused** leftovers kept to preserve migration history: `Example`,
`Upload`.

## Status derivation (not stored — computed)

`monitor_status($lastIsOk, $daysLeft)` →
- `unknown` — never checked yet (`LastIsOk` null)
- `failed` — last check ran but errored (`LastIsOk` = 0; host unreachable, no TLS)
- `expired` — `days_left < 0` (already past expiry)
- `critical` — `days_left` 0–7 (urgent but still valid)
- `warning` — `days_left <= 30`
- `healthy` — otherwise
