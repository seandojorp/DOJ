-- =============================================================================================
-- Migration 001 (OPTIONAL — NOT REQUIRED, NOT APPLIED BY THE APPLICATION)
-- Purpose : Store "Type of L&D" and "Conducted/Sponsored By" as text instead of numeric codes.
-- Why     : In the authoritative schema both columns are INT NOT NULL, so the application stores a
--           numeric code and shows a label from config/config.php ('lookups'). That works as is.
--           Apply this only if your office prefers free-text values (e.g. a specific sponsor name).
-- Effect  : Existing codes are converted to their labels. After applying, you must ALSO change the
--           two fields in app/Services/Modules.php ('learning' section) from 'select' to text.
-- Safety  : Non-destructive (no rows deleted). BACK UP FIRST:
--             mysqldump -u root -p doj-employee > doj-employee-backup-before-001.sql
--           Edit the CASE labels below to match config/config.php before running.
-- =============================================================================================
START TRANSACTION;

ALTER TABLE `learning_development`
  ADD COLUMN `Type_Text` VARCHAR(255) NULL AFTER `Type`,
  ADD COLUMN `Conducted_Sponsored_Text` VARCHAR(255) NULL AFTER `Conducted_Sponsored`;

UPDATE `learning_development` SET
  `Type_Text` = CASE `Type` WHEN 1 THEN 'Managerial' WHEN 2 THEN 'Supervisory' WHEN 3 THEN 'Technical'
                            WHEN 4 THEN 'Foundation' ELSE 'Others' END,
  `Conducted_Sponsored_Text` = CASE `Conducted_Sponsored`
                            WHEN 1 THEN 'Department of Justice (DOJ)' WHEN 2 THEN 'National Prosecution Service (NPS)'
                            WHEN 3 THEN 'Civil Service Commission (CSC)' WHEN 4 THEN 'Other Government Agency'
                            WHEN 5 THEN 'Private / Non-Government Organization' WHEN 6 THEN 'International / Foreign Organization'
                            ELSE 'Others' END;

ALTER TABLE `learning_development`
  DROP COLUMN `Type`,
  DROP COLUMN `Conducted_Sponsored`,
  CHANGE COLUMN `Type_Text` `Type` VARCHAR(255) NOT NULL,
  CHANGE COLUMN `Conducted_Sponsored_Text` `Conducted_Sponsored` VARCHAR(255) NOT NULL;

COMMIT;
-- Note: MariaDB commits DDL implicitly; if any statement fails, restore from the backup.
