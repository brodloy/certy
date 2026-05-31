-- Lookup: the day-tiers at which expiry warnings fire. Data-driven so tiers
-- can be added/changed/disabled without touching code. IsActive lets you
-- switch a tier off without deleting its history references.
CREATE TABLE `LK_AlertThreshold` (
    `PK_AlertThresholdID` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `Days`                SMALLINT UNSIGNED NOT NULL,
    `Label`               VARCHAR(30) NOT NULL,
    `IsActive`            TINYINT(1)  NOT NULL DEFAULT 1,
    PRIMARY KEY (`PK_AlertThresholdID`),
    UNIQUE KEY `uq_alertthreshold_days` (`Days`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `LK_AlertThreshold` (`PK_AlertThresholdID`, `Days`, `Label`, `IsActive`) VALUES
    (1, 30, '30 days', 1),
    (2, 14, '14 days', 1),
    (3, 7,  '7 days',  1),
    (4, 1,  '1 day',   1);
