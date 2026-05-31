CREATE TABLE `Example` (
    `PK_ExampleID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `FK_UserID`    INT UNSIGNED NOT NULL,
    `Title`        VARCHAR(200) NOT NULL,
    `Body`         TEXT NULL,
    `Status`       VARCHAR(20)  NOT NULL DEFAULT 'active',
    `CreatedAt`    DATETIME     NOT NULL,
    `UpdatedAt`    DATETIME     NOT NULL,
    PRIMARY KEY (`PK_ExampleID`),
    KEY `idx_example_user` (`FK_UserID`),
    CONSTRAINT `fk_example_user` FOREIGN KEY (`FK_UserID`)
        REFERENCES `User` (`PK_UserID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
