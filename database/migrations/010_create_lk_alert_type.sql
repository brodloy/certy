-- Lookup: which kind of alert was sent. Distinguishes the dispatcher's two
-- paths and leaves room for future kinds (recovered, renewed, ...).
CREATE TABLE `LK_AlertType` (
    `PK_AlertTypeID` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `Code`           VARCHAR(30) NOT NULL,
    `Label`          VARCHAR(50) NOT NULL,
    PRIMARY KEY (`PK_AlertTypeID`),
    UNIQUE KEY `uq_alerttype_code` (`Code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `LK_AlertType` (`PK_AlertTypeID`, `Code`, `Label`) VALUES
    (1, 'expiry',        'Expiry warning'),
    (2, 'check_failure', 'Check failure');
