CREATE TABLE `OAuthIdentity` (
    `PK_OAuthIdentityID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `FK_UserID`          INT UNSIGNED NOT NULL,
    `Provider`           VARCHAR(30)  NOT NULL,
    `ProviderUserID`     VARCHAR(190) NOT NULL,
    `CreatedAt`          DATETIME     NOT NULL,
    PRIMARY KEY (`PK_OAuthIdentityID`),
    UNIQUE KEY `uq_oauth` (`Provider`, `ProviderUserID`),
    KEY `idx_oauth_user` (`FK_UserID`),
    CONSTRAINT `fk_oauth_user` FOREIGN KEY (`FK_UserID`)
        REFERENCES `User` (`PK_UserID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
