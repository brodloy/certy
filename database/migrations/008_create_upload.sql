CREATE TABLE `Upload` (
    `PK_UploadID`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `FK_UserID`    INT UNSIGNED NOT NULL,
    `StoredName`   VARCHAR(80)  NOT NULL,
    `OriginalName` VARCHAR(255) NOT NULL,
    `Mime`         VARCHAR(100) NOT NULL,
    `Size`         INT UNSIGNED NOT NULL,
    `CreatedAt`    DATETIME     NOT NULL,
    PRIMARY KEY (`PK_UploadID`),
    KEY `idx_upload_user` (`FK_UserID`),
    CONSTRAINT `fk_upload_user` FOREIGN KEY (`FK_UserID`)
        REFERENCES `User` (`PK_UserID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
