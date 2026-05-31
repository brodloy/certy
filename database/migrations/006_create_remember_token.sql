CREATE TABLE `RememberToken` (
    `PK_RememberTokenID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `FK_UserID`          INT UNSIGNED NOT NULL,
    `TokenHash`          CHAR(64)     NOT NULL,
    `ExpiresAt`          DATETIME     NOT NULL,
    `CreatedAt`          DATETIME     NOT NULL,
    PRIMARY KEY (`PK_RememberTokenID`),
    KEY `idx_remember_user` (`FK_UserID`),
    CONSTRAINT `fk_remember_user` FOREIGN KEY (`FK_UserID`)
        REFERENCES `User` (`PK_UserID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
