CREATE TABLE `LoginAttempt` (
    `PK_LoginAttemptID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `Identifier`        VARCHAR(190) NOT NULL,
    `IPAddress`         VARCHAR(45)  NOT NULL,
    `CreatedAt`         DATETIME     NOT NULL,
    PRIMARY KEY (`PK_LoginAttemptID`),
    KEY `idx_attempt_lookup` (`Identifier`, `CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
