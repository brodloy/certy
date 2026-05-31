CREATE TABLE `EmailVerification` (
    `PK_EmailVerificationID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `FK_UserID`              INT UNSIGNED NOT NULL,
    `TokenHash`              CHAR(64)     NOT NULL,
    `ExpiresAt`              DATETIME     NOT NULL,
    `UsedAt`                 DATETIME     NULL,
    `CreatedAt`              DATETIME     NOT NULL,
    PRIMARY KEY (`PK_EmailVerificationID`),
    KEY `idx_verify_user` (`FK_UserID`),
    CONSTRAINT `fk_verify_user` FOREIGN KEY (`FK_UserID`)
        REFERENCES `User` (`PK_UserID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
