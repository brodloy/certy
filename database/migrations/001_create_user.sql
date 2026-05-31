CREATE TABLE `User` (
    `PK_UserID`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `Name`         VARCHAR(120) NOT NULL,
    `Email`        VARCHAR(190) NOT NULL,
    `PasswordHash` VARCHAR(255) NOT NULL,
    `Role`         VARCHAR(20)  NOT NULL DEFAULT 'user',
    `Active`       TINYINT(1)   NOT NULL DEFAULT 1,
    `VerifiedAt`   DATETIME     NULL,
    `CreatedAt`    DATETIME     NOT NULL,
    `UpdatedAt`    DATETIME     NOT NULL,
    PRIMARY KEY (`PK_UserID`),
    UNIQUE KEY `uq_user_email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
