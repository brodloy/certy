# certy.io — Database

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

## certy.io tables

### Lookup tables (seeded in their migrations)

**`LK_TargetType`** — kinds of thing we monitor.
| Column | Type | Notes |
|---|---|---|
| `PK_TargetTypeID` | TINYINT UNSIGNED PK | |
| `Code` | VARCHAR(20) UNIQUE | `ssl`, `domain` |
| `Label` | VARCHAR(50) | display name |
Seeded: `1=ssl`, `2=domain`.

**`LK_AlertType`** — kind of alert (for the deferred alerting feature).
Seeded: `1=expiry`, `2=check_failure`.

**`LK_AlertThreshold`** — day-tiers for expiry warnings (deferred feature).
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

### `AlertLog` — dedup ledger (for the deferred alerting feature)

| Column | Type | Notes |
|---|---|---|
| `PK_AlertLogID` | INT UNSIGNED PK AI | |
| `FK_MonitoredTargetID` | INT UNSIGNED | → `MonitoredTarget`, cascade |
| `FK_AlertTypeID` | TINYINT UNSIGNED | → `LK_AlertType` |
| `FK_AlertThresholdID` | TINYINT UNSIGNED NULL | → `LK_AlertThreshold`; null for failure alerts |
| `ExpiresAtSnapshot` | DATETIME NULL | the expiry an alert fired against — a changed value = new cycle = alerts fire fresh after renewal |
| `SentAt` | DATETIME | UTC |

## Starter tables (inherited, not certy-specific)

`User`, `PasswordReset`, `LoginAttempt`, `EmailVerification`, `RememberToken`,
`OAuthIdentity` (generic `Provider` + `ProviderUserID` — provider-agnostic),
plus two **unused** leftovers kept to preserve migration history: `Example`,
`Upload`.

## Status derivation (not stored — computed)

`monitor_status($lastIsOk, $daysLeft)` →
- `unknown` — never checked, or last check failed (`LastIsOk` null or 0)
- `critical` — `days_left <= 7` (or expired/negative)
- `warning` — `days_left <= 30`
- `healthy` — otherwise
