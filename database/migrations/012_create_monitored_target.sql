-- One row per thing a user watches. Per-user ownership (FK_UserID) is the
-- privacy boundary. The Last* columns are a DELIBERATE denormalisation of the
-- most recent CheckResult, so the dashboard reads only this table (one indexed
-- query per user, no join to history).
CREATE TABLE `MonitoredTarget` (
    `PK_MonitoredTargetID` INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `FK_UserID`            INT UNSIGNED     NOT NULL,
    `FK_TargetTypeID`      TINYINT UNSIGNED NOT NULL,
    `Host`                 VARCHAR(255)     NOT NULL,
    `Port`                 SMALLINT UNSIGNED NOT NULL DEFAULT 443,  -- SSL only
    `Label`                VARCHAR(255)     NULL,
    `IsActive`             TINYINT(1)       NOT NULL DEFAULT 1,
    -- Denormalised snapshot of the latest check (fast dashboard reads):
    `LastCheckedAt`        DATETIME         NULL,
    `LastIsOk`             TINYINT(1)       NULL,
    `LastExpiresAt`        DATETIME         NULL,
    `LastDaysLeft`         INT              NULL,   -- signed: negative = expired
    `CreatedAt`            DATETIME         NOT NULL,
    `UpdatedAt`            DATETIME         NOT NULL,
    PRIMARY KEY (`PK_MonitoredTargetID`),
    UNIQUE KEY `uq_target_user_host_type` (`FK_UserID`, `Host`, `FK_TargetTypeID`),
    KEY `idx_target_user` (`FK_UserID`),
    KEY `idx_target_type` (`FK_TargetTypeID`),
    CONSTRAINT `fk_target_user` FOREIGN KEY (`FK_UserID`)
        REFERENCES `User` (`PK_UserID`) ON DELETE CASCADE,
    CONSTRAINT `fk_target_type` FOREIGN KEY (`FK_TargetTypeID`)
        REFERENCES `LK_TargetType` (`PK_TargetTypeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
