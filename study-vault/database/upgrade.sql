-- ============================================================================
-- Study Vault: UPGRADE an existing database (run ONCE)
--
-- For installs that already have the old `study_vault` database. Existing
-- research, students, authors, categories and logs are preserved.
-- Back up first (phpMyAdmin > Export). In phpMyAdmin select the `study_vault`
-- database and use Import (or paste into the SQL tab).
--
-- After running this file:
--   1. Existing research stays Approved (visible), future uploads are Pending.
--   2. Existing students stay Approved but have NO email yet. Set each login
--      email on Admin > Students (they log in with Email + Password).
--   3. Manuscript PDFs now live in storage/manuscripts/ (see README).
-- ============================================================================

USE `study_vault`;
SET NAMES utf8mb4;

-- ---- Programs: mark which programs may register ---------------------------
ALTER TABLE `programs` ADD COLUMN `is_it_program` tinyint(1) NOT NULL DEFAULT 0;
UPDATE `programs` SET `is_it_program` = 1 WHERE `program_name` LIKE '%Information Technology%' AND `program_name` NOT LIKE 'Other%';
INSERT IGNORE INTO `programs` (`program_name`, `is_it_program`) VALUES
('Bachelor of Science in Business Administration', 0),
('Bachelor of Secondary Education', 0),
('Other program (not Information Technology)', 0);

-- ---- Students: full name, program, approval links ------------------------
ALTER TABLE `students`
  ADD COLUMN `full_name` varchar(160) NOT NULL DEFAULT '' AFTER `student_number`,
  ADD COLUMN `program_id` int DEFAULT NULL AFTER `user_type`,
  MODIFY `first_name` varchar(80) DEFAULT NULL,
  MODIFY `last_name` varchar(80) DEFAULT NULL;

UPDATE `students` SET `full_name` = TRIM(CONCAT(IFNULL(`first_name`, ''), ' ', IFNULL(`last_name`, ''))) WHERE `full_name` = '';
UPDATE `students` SET `status` = 'pending' WHERE `status` IS NULL;
UPDATE `students` SET `user_type` = 'enrolled' WHERE `user_type` IS NULL;
UPDATE `students` SET `program_id` = (SELECT `program_id` FROM (SELECT `program_id` FROM `programs` WHERE `is_it_program` = 1 ORDER BY `program_id` LIMIT 1) AS p) WHERE `program_id` IS NULL;

ALTER TABLE `students`
  MODIFY `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  MODIFY `user_type` enum('enrolled','alumni') NOT NULL DEFAULT 'enrolled',
  ADD KEY `idx_student_fullname` (`full_name`),
  ADD CONSTRAINT `fk_student_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_admin` FOREIGN KEY (`approved_by`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL;
-- (students.email already has a UNIQUE key in the original schema.)

-- ---- Research: approval workflow + future submission fields ---------------
ALTER TABLE `research`
  MODIFY `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  ADD COLUMN `uploaded_by_admin_id` int DEFAULT NULL,
  ADD COLUMN `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `reviewed_by` int DEFAULT NULL,
  ADD COLUMN `reviewed_at` datetime DEFAULT NULL,
  ADD COLUMN `review_note` varchar(255) DEFAULT NULL;

UPDATE `research` SET `status` = 'pending' WHERE `status` IS NULL;
UPDATE `research` SET `submitted_at` = `created_at`;
UPDATE `research` SET `file_path` = REPLACE(`file_path`, 'assets/uploads/', 'storage/manuscripts/') WHERE `file_path` LIKE 'assets/uploads/%';

ALTER TABLE `research`
  ADD CONSTRAINT `fk_research_student` FOREIGN KEY (`submitted_by`) REFERENCES `students` (`student_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_research_uploader` FOREIGN KEY (`uploaded_by_admin_id`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_research_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL;

-- ---- Activity logs: record student activity too ---------------------------
ALTER TABLE `activity_logs`
  ADD COLUMN `student_id` int DEFAULT NULL AFTER `admin_id`,
  ADD COLUMN `ip_address` varchar(45) DEFAULT NULL,
  ADD KEY `fk_log_student` (`student_id`),
  ADD CONSTRAINT `fk_log_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE SET NULL;

-- ---- New tables -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `access_plans` (
  `plan_id` int NOT NULL AUTO_INCREMENT,
  `plan_name` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration_days` int NOT NULL DEFAULT 0,
  `full_access` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = may view full manuscripts',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`plan_id`),
  UNIQUE KEY `plan_name` (`plan_name`),
  KEY `idx_plan_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `access_plans` (`plan_id`, `plan_name`, `description`, `price`, `duration_days`, `full_access`, `status`, `sort_order`) VALUES
(1, 'Free Access', 'Browse and search approved research and read abstracts.', 0.00, 0, 0, 'active', 1),
(2, 'Research Access', 'Everything in Free Access, plus full manuscript viewing for 30 days.', 99.00, 30, 1, 'active', 2),
(3, 'Research Access Plus', 'Everything in Research Access with a longer 90-day reading period.', 249.00, 90, 1, 'active', 3);

CREATE TABLE IF NOT EXISTS `subscriptions` (
  `subscription_id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `status` enum('pending','active','expired','cancelled') NOT NULL DEFAULT 'pending',
  `reference_code` varchar(40) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_mode` varchar(20) NOT NULL DEFAULT 'demo' COMMENT 'demo = prototype checkout, no real payment',
  `start_date` datetime DEFAULT NULL,
  `expiration_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`subscription_id`),
  UNIQUE KEY `reference_code` (`reference_code`),
  KEY `idx_sub_student` (`student_id`,`status`,`expiration_date`),
  KEY `fk_sub_plan` (`plan_id`),
  CONSTRAINT `fk_sub_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_plan` FOREIGN KEY (`plan_id`) REFERENCES `access_plans` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `attempt_id` int NOT NULL AUTO_INCREMENT,
  `identifier` varchar(120) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attempt_id`),
  KEY `idx_attempt_identifier` (`identifier`,`attempted_at`),
  KEY `idx_attempt_ip` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
