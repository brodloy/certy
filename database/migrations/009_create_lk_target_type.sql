-- Lookup: the kinds of thing we monitor. Expandable (add a row for a new
-- check type later — e.g. http_health — with no schema change).
CREATE TABLE `LK_TargetType` (
    `PK_TargetTypeID` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `Code`            VARCHAR(20)  NOT NULL,   -- machine value used in code
    `Label`           VARCHAR(50)  NOT NULL,   -- display name
    PRIMARY KEY (`PK_TargetTypeID`),
    UNIQUE KEY `uq_targettype_code` (`Code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `LK_TargetType` (`PK_TargetTypeID`, `Code`, `Label`) VALUES
    (1, 'ssl',    'SSL Certificate'),
    (2, 'domain', 'Domain');
