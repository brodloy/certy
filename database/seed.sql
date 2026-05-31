-- ============================================================================
-- SEED — demo data. Run after migrations (php console db:install).
--   demo@example.com  / password   (regular user, email verified)
--   admin@example.com / password   (admin)
-- The hash below is a real argon2id hash of the word "password".
--
-- Safe to run more than once: INSERT IGNORE skips rows whose unique key
-- already exists, so a repeated db:install won't error or duplicate data.
-- ============================================================================

INSERT IGNORE INTO `User` (`Name`, `Email`, `PasswordHash`, `Role`, `Active`, `VerifiedAt`, `CreatedAt`, `UpdatedAt`) VALUES
('Demo User',  'demo@example.com',  '$argon2id$v=19$m=65536,t=4,p=1$SC5SZzlSTjFDSWw4WENmOQ$xMDiV+wt4UsPvSFC5Y+EHjfK+zVQvaitpH4+xFPEDNg', 'user',  1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('Admin User', 'admin@example.com', '$argon2id$v=19$m=65536,t=4,p=1$SC5SZzlSTjFDSWw4WENmOQ$xMDiV+wt4UsPvSFC5Y+EHjfK+zVQvaitpH4+xFPEDNg', 'admin', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP());

SET @uid = (SELECT `PK_UserID` FROM `User` WHERE `Email` = 'demo@example.com');

-- A few demo targets for the demo user so the dashboard isn't empty on first
-- login. They start unchecked — press "Scan all" to populate their status.
--   FK_TargetTypeID: 1 = ssl, 2 = domain  (see LK_TargetType)
INSERT IGNORE INTO `MonitoredTarget`
    (`FK_UserID`, `FK_TargetTypeID`, `Host`, `Port`, `Label`, `IsActive`, `CreatedAt`, `UpdatedAt`) VALUES
(@uid, 1, 'github.com',     443, 'GitHub (SSL)',        1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
(@uid, 1, 'cloudflare.com', 443, 'Cloudflare (SSL)',    1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
(@uid, 2, 'example.com',    443, 'example.com (domain)',1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
