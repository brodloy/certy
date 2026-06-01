-- Track WHERE each check came from and WHICH scheduled run produced it, so the
-- admin overview can separate user-triggered (dashboard) scans from scheduled
-- ones, and drill into exactly what a given monitor:run checked.
--   Source           : 'scheduled' (cron / monitor:run) | 'manual' (dashboard "Scan")
--   FK_MonitorRunID  : the MonitorRun that produced this check; NULL for manual
--                      scans and queue-worker checks (not tied to a single run row).
-- Existing rows default to 'scheduled' (historical approximation).
ALTER TABLE `CheckResult`
    ADD COLUMN `Source`          VARCHAR(16)  NOT NULL DEFAULT 'scheduled' AFTER `IsOk`,
    ADD COLUMN `FK_MonitorRunID` INT UNSIGNED NULL              AFTER `Source`,
    ADD KEY `idx_check_checked` (`CheckedAt`),
    ADD KEY `idx_check_run` (`FK_MonitorRunID`),
    ADD CONSTRAINT `fk_check_run` FOREIGN KEY (`FK_MonitorRunID`)
        REFERENCES `MonitorRun` (`PK_MonitorRunID`) ON DELETE SET NULL;
