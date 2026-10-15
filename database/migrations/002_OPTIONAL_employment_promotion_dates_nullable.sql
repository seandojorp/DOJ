-- =============================================================================================
-- Migration 002 (OPTIONAL — NOT REQUIRED, NOT APPLIED BY THE APPLICATION)
-- Purpose : Allow employees who have never been promoted to leave promotion dates empty.
-- Why     : `employment-information`.Date_Previous_Promotion and Date_Latest_Promotion are DATE NOT NULL.
--           The application currently asks such employees to enter their date of entry instead.
-- Effect  : Makes the two columns nullable. After applying, mark the two fields optional in
--           app/Services/Modules.php ('employment' section) and allow empty values in validation.
-- Safety  : Non-destructive. BACK UP FIRST:
--             mysqldump -u root -p doj-employee > doj-employee-backup-before-002.sql
-- =============================================================================================
ALTER TABLE `employment-information`
  MODIFY `Date_Previous_Promotion` DATE NULL,
  MODIFY `Date_Latest_Promotion` DATE NULL;
