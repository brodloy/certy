-- One row per `console monitor:run` invocation, so you can confirm the scheduler
-- is actually firing and see what each run did. Written by the command itself
-- (CLI only) — nothing in the web app reads or writes this yet. A "nothing due"
-- run is still recorded (CheckedCount = 0): proof the run happened. Not tied to a
-- target, so no foreign key; pure append-only operational log.
CREATE TABLE `MonitorRun` (
    `PK_MonitorRunID` INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `StartedAt`       DATETIME      NOT NULL,            -- UTC, when the run began
    `Mode`            VARCHAR(10)   NOT NULL,            -- 'due' | 'full'
    `DueCount`        SMALLINT UNSIGNED NULL,            -- targets selected as due ('due' mode); NULL for 'full'
    `CheckedCount`    SMALLINT UNSIGNED NOT NULL,        -- targets actually checked
    `OkCount`         SMALLINT UNSIGNED NOT NULL,
    `FailedCount`     SMALLINT UNSIGNED NOT NULL,
    `DurationMs`      INT UNSIGNED  NOT NULL,            -- wall-clock of the run
    PRIMARY KEY (`PK_MonitorRunID`),
    KEY `idx_monitor_run_started` (`StartedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
