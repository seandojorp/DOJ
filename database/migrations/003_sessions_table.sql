-- Required only when sessions are stored in the database (the default on Vercel).
-- Run once against the application database. Safe to re-run.
CREATE TABLE IF NOT EXISTS `ems_sessions` (
  `Session_ID` VARCHAR(128) NOT NULL,
  `Data`       MEDIUMBLOB   NOT NULL,
  `Expires_At` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`Session_ID`),
  KEY `idx_ems_sessions_expires` (`Expires_At`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
