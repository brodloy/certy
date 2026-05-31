-- Dedup ledger. alreadyAlerted() checks for a row matching target + tier + the
-- expiry it fired against (ExpiresAtSnapshot). When the expiry moves forward
-- (a renewal), the snapshot differs, so the next cycle's alerts fire fresh.
CREATE TABLE `AlertLog` (
    `PK_AlertLogID`        INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `FK_MonitoredTargetID` INT UNSIGNED     NOT NULL,
    `FK_AlertTypeID`       TINYINT UNSIGNED NOT NULL,
    `FK_AlertThresholdID`  TINYINT UNSIGNED NULL,     -- null for check_failure alerts
    `ExpiresAtSnapshot`    DATETIME         NULL,     -- the expiry this alert fired against
    `SentAt`               DATETIME         NOT NULL,
    PRIMARY KEY (`PK_AlertLogID`),
    KEY `idx_alert_dedup` (`FK_MonitoredTargetID`, `FK_AlertTypeID`, `FK_AlertThresholdID`),
    CONSTRAINT `fk_alert_target` FOREIGN KEY (`FK_MonitoredTargetID`)
        REFERENCES `MonitoredTarget` (`PK_MonitoredTargetID`) ON DELETE CASCADE,
    CONSTRAINT `fk_alert_type` FOREIGN KEY (`FK_AlertTypeID`)
        REFERENCES `LK_AlertType` (`PK_AlertTypeID`),
    CONSTRAINT `fk_alert_threshold` FOREIGN KEY (`FK_AlertThresholdID`)
        REFERENCES `LK_AlertThreshold` (`PK_AlertThresholdID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
