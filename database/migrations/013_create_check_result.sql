-- Full history: one row per check run (every run persists). The dashboard does
-- NOT read this (it reads MonitoredTarget.Last*); the per-target history view
-- does. No stored status — urgency is derived from DaysLeft/IsOk at render time
-- so it can never go stale.
CREATE TABLE `CheckResult` (
    `PK_CheckResultID`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `FK_MonitoredTargetID` INT UNSIGNED NOT NULL,
    `IsOk`                 TINYINT(1)   NOT NULL,     -- did the check itself succeed
    `ExpiresAt`            DATETIME     NULL,         -- null on failure
    `DaysLeft`             INT          NULL,         -- null on failure
    `Issuer`               VARCHAR(255) NULL,         -- ssl only
    `Subject`              VARCHAR(255) NULL,         -- ssl only
    `ErrorText`            VARCHAR(500) NULL,         -- populated on failure
    `CheckedAt`            DATETIME     NOT NULL,
    PRIMARY KEY (`PK_CheckResultID`),
    KEY `idx_check_target_time` (`FK_MonitoredTargetID`, `CheckedAt`),
    CONSTRAINT `fk_check_target` FOREIGN KEY (`FK_MonitoredTargetID`)
        REFERENCES `MonitoredTarget` (`PK_MonitoredTargetID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
