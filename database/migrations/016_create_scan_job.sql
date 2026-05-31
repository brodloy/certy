-- ScanJob — a DB-backed work queue that decouples scan DISCOVERY from EXECUTION
-- so the scanner can scale horizontally. `monitor:enqueue` inserts due targets;
-- many `monitor:work` processes claim batches concurrently and run the checks.
--
-- Concurrency is safe without SKIP LOCKED (works on MySQL 5.7+): a worker claims
-- rows by stamping its unique ClaimedBy token via UPDATE ... LIMIT, then SELECTs
-- exactly the rows carrying its token. Completed jobs are DELETED (this is a
-- queue, not a log — CheckResult/MonitorRun are the history). Attempts caps
-- poison jobs; stale 'running' rows are reclaimable.
CREATE TABLE `ScanJob` (
    `PK_ScanJobID`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `FK_MonitoredTargetID` INT UNSIGNED     NOT NULL,
    `Status`               VARCHAR(10)      NOT NULL DEFAULT 'pending', -- pending | running | failed
    `ClaimedBy`            VARCHAR(40)       NULL,                      -- worker token
    `ClaimedAt`            DATETIME          NULL,
    `Attempts`             TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `CreatedAt`            DATETIME         NOT NULL,
    PRIMARY KEY (`PK_ScanJobID`),
    KEY `idx_scanjob_claim` (`Status`, `PK_ScanJobID`),
    KEY `idx_scanjob_target` (`FK_MonitoredTargetID`),
    CONSTRAINT `fk_scanjob_target` FOREIGN KEY (`FK_MonitoredTargetID`)
        REFERENCES `MonitoredTarget` (`PK_MonitoredTargetID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
