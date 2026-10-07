-- ============================================================================
-- Study Vault: Thesis and Research System  --  FULL DATABASE (fresh install)
-- Import this file in phpMyAdmin (Import tab). It creates the `study_vault`
-- database, every table, and sample/demo data.
--
-- Already have the old Study Vault database? Do NOT import this file.
-- Run database/upgrade.sql instead (it upgrades your existing data in place).
--
-- DEMO ACCOUNTS (development only - delete before real use)
--   Administrator : admin  / (unchanged from your existing install)
--   Students      : password for all demo students is  Student123!
--     free@studyvault.test     approved, Free Access
--     paid@studyvault.test     approved, active Research Access plan
--     expired@studyvault.test  approved, subscription EXPIRED
--     pending@studyvault.test  pending approval (cannot log in)
--     rejected@studyvault.test rejected (cannot log in)
--   The 9 original student accounts are kept but have no email yet:
--   an administrator sets their login email on Admin > Students.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `study_vault` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `study_vault`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Administrators
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `administrators` (
  `admin_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'admin',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `administrators` (`admin_id`, `name`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'Development Administrator', 'admin', '$2y$10$jQQ1EJrvsr.Z4a1QJMB2M.YWMa/qE1YbrtLn1CTQECiqiaU/YChai', 'admin', '2026-09-17 23:45:22');

-- ---------------------------------------------------------------------------
-- Programs (is_it_program = 1 means the program may register)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `programs` (
  `program_id` int NOT NULL AUTO_INCREMENT,
  `program_name` varchar(150) NOT NULL,
  `is_it_program` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`program_id`),
  UNIQUE KEY `program_name` (`program_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `programs` (`program_id`, `program_name`, `is_it_program`) VALUES
(1, 'Bachelor of Science in Information Technology', 1),
(2, 'Bachelor of Science in Business Administration', 0),
(3, 'Bachelor of Secondary Education', 0),
(4, 'Other program (not Information Technology)', 0);

-- ---------------------------------------------------------------------------
-- Categories and authors
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `category_name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'Web and Mobile Application Development', 'Research related to web systems, mobile applications and application development.'),
(2, 'Networking Security', 'Research related to networking, network administration, cybersecurity and security.');

CREATE TABLE IF NOT EXISTS `authors` (
  `author_id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(180) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`author_id`),
  UNIQUE KEY `full_name` (`full_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `authors` (`author_id`, `full_name`, `created_at`) VALUES
(1, 'Sample Researcher A', '2026-09-17 23:45:22'),
(2, 'Sample Researcher B', '2026-09-17 23:45:22'),
(3, 'Sample Researcher C', '2026-09-17 23:45:22');

-- ---------------------------------------------------------------------------
-- Students (login = email + password; student_number kept for verification)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students` (
  `student_id` int NOT NULL AUTO_INCREMENT,
  `student_number` varchar(50) NOT NULL,
  `full_name` varchar(160) NOT NULL,
  `first_name` varchar(80) DEFAULT NULL COMMENT 'legacy, unused',
  `last_name` varchar(80) DEFAULT NULL COMMENT 'legacy, unused',
  `email` varchar(120) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('enrolled','alumni') NOT NULL DEFAULT 'enrolled',
  `program_id` int DEFAULT NULL,
  `access_level` enum('free','verified') DEFAULT 'free' COMMENT 'legacy, unused - access now comes from subscriptions',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `graduation_year` smallint DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `student_number` (`student_number`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_student_name` (`last_name`,`first_name`),
  KEY `idx_student_fullname` (`full_name`),
  KEY `idx_status` (`status`),
  KEY `idx_access` (`access_level`),
  KEY `fk_student_program` (`program_id`),
  KEY `fk_student_admin` (`approved_by`),
  CONSTRAINT `fk_student_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_student_admin` FOREIGN KEY (`approved_by`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Original accounts (kept; they need a login email set by an administrator)
INSERT INTO `students` (`student_id`, `student_number`, `full_name`, `first_name`, `last_name`, `email`, `password`, `user_type`, `program_id`, `access_level`, `status`, `created_at`) VALUES
(1, '24-01048', 'Johnbrix Gantala', 'Johnbrix', 'Gantala', NULL, '$2y$10$15H.uDdigvG2xenNhkA7BekDq/S9ObX2ZjsaO62V1Eov3n9hoapA.', 'enrolled', 1, 'verified', 'approved', '2026-09-17 23:45:31'),
(3, '24-01049', 'Vince Jay Gantala', 'Vince Jay', 'Gantala', NULL, '$2y$10$nv/DUERH3eNiueaOoq.AdOUBb79Yodyv89SiuyupiiBkxjOXwq4Sm', 'enrolled', 1, 'verified', 'approved', '2026-09-18 09:52:08'),
(4, '24-01050', 'Rendel Pauitan', 'Rendel', 'Pauitan', NULL, '$2y$10$YMSwysPC4YgyvZ9mFm2R.OGN2teBj/fSCy3R.Rk4BrG1jdgGU4PL6', 'enrolled', 1, 'verified', 'approved', '2026-09-18 12:35:55'),
(5, '24-01051', 'sule ka', 'sule', 'ka', NULL, '$2y$10$w0qW9aNB61ug7EkgjNGBdeXciGZ16SWDdklwxo248qZ4m/omEm1zC', 'enrolled', 1, 'verified', 'approved', '2026-09-19 12:15:40'),
(6, '26-01037', 'Mhel To', 'Mhel', 'To', NULL, '$2y$10$ydQYzMwGkUl48TeDS9o6SeuK8ssQQxxUvoU0OAV6tX/5qhLcWANxC', 'enrolled', 1, 'verified', 'approved', '2026-09-19 17:19:57'),
(7, '24-01052', 'Aljon Peralta', 'Aljon', 'Peralta', NULL, '$2y$10$gkSfkRi29oE19xqg6W6Jxeq4dT/XRW3BXiWKQL5V1HegQpwcsXWGK', 'enrolled', 1, 'verified', 'approved', '2026-09-25 13:44:21'),
(8, '24-01053', 'Justine Elijah Tumolva', 'Justine Elijah', 'Tumolva', NULL, '$2y$10$sFfHU9V.a6nocFYRJAzMlu6utDH8jARBy9h45W1ZsWr2ofaY/IKNq', 'enrolled', 1, 'verified', 'approved', '2026-09-25 14:30:01'),
(9, '24-01054', 'Karl Vincent Adarlo', 'Karl Vincent', 'Adarlo', NULL, '$2y$10$zoxAIjvjm5wZ52d9Uu3fgeVZhPIuPbFqJCacbmsZlpc3aIEhuhwlW', 'enrolled', 1, 'verified', 'approved', '2026-09-25 15:02:49'),
(10, '24-01081', 'Aljon Peralta', 'Aljon', 'Peralta', NULL, '$2y$10$PWSuFnQokVN2RUzPp0vCHuy9gcJ5O2cASmAwlvlk2Hq.nBuix8BJ6', 'enrolled', 1, 'verified', 'approved', '2026-09-30 15:47:24');

-- Demo accounts (password: Student123!)
INSERT INTO `students` (`student_id`, `student_number`, `full_name`, `email`, `password`, `user_type`, `program_id`, `status`, `approved_by`, `approved_at`, `created_at`) VALUES
(11, 'DEMO-0001', 'Demo Free Student',     'free@studyvault.test',     '$2y$10$TybSxSeYHSAJ1KQFBik5zu55FmnXa4hbldYcuXS6bV7bNxtQ5ReMe', 'enrolled', 1, 'approved', 1, NOW(), NOW()),
(12, 'DEMO-0002', 'Demo Paid Student',     'paid@studyvault.test',     '$2y$10$TybSxSeYHSAJ1KQFBik5zu55FmnXa4hbldYcuXS6bV7bNxtQ5ReMe', 'enrolled', 1, 'approved', 1, NOW(), NOW()),
(13, 'DEMO-0003', 'Demo Expired Alumni',   'expired@studyvault.test',  '$2y$10$TybSxSeYHSAJ1KQFBik5zu55FmnXa4hbldYcuXS6bV7bNxtQ5ReMe', 'alumni',   1, 'approved', 1, NOW(), NOW()),
(14, 'DEMO-0004', 'Demo Pending Student',  'pending@studyvault.test',  '$2y$10$TybSxSeYHSAJ1KQFBik5zu55FmnXa4hbldYcuXS6bV7bNxtQ5ReMe', 'enrolled', 1, 'pending',  NULL, NULL, NOW()),
(15, 'DEMO-0005', 'Demo Rejected Student', 'rejected@studyvault.test', '$2y$10$TybSxSeYHSAJ1KQFBik5zu55FmnXa4hbldYcuXS6bV7bNxtQ5ReMe', 'enrolled', 1, 'rejected', 1, NOW(), NOW());

-- ---------------------------------------------------------------------------
-- Research (status: pending -> approved | rejected)
-- file_path points into storage/manuscripts/, which Apache refuses to serve.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `research` (
  `research_id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `abstract` text DEFAULT NULL,
  `year` smallint NOT NULL,
  `published_date` date NOT NULL,
  `keywords` varchar(500) DEFAULT NULL,
  `category_id` int NOT NULL,
  `program_id` int DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_by` int DEFAULT NULL COMMENT 'student who submitted (future graduation-submission module)',
  `uploaded_by_admin_id` int DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by` int DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL COMMENT 'admin approval / rejection date',
  `review_note` varchar(255) DEFAULT NULL,
  `view_count` int NOT NULL DEFAULT 0,
  `download_count` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`research_id`),
  KEY `idx_title` (`title`),
  KEY `idx_year` (`year`),
  KEY `idx_published` (`published_date`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `fk_research_program` (`program_id`),
  KEY `fk_research_student` (`submitted_by`),
  KEY `fk_research_uploader` (`uploaded_by_admin_id`),
  KEY `fk_research_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_research_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_research_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_research_student` FOREIGN KEY (`submitted_by`) REFERENCES `students` (`student_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_research_uploader` FOREIGN KEY (`uploaded_by_admin_id`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_research_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `research` (`research_id`, `title`, `abstract`, `year`, `published_date`, `keywords`, `category_id`, `program_id`, `file_path`, `status`, `submitted_by`, `view_count`, `download_count`, `created_at`, `updated_at`) VALUES
(1, 'Sample Web-Based Research Repository', 'This study presents the design and development of a web-based research repository for organizing, searching, and accessing approved academic studies. The proposed system centralizes research records, author information, categories, keywords, publication dates, and document files in one platform. It provides students with a searchable interface for discovering related studies and viewing available research documents, while authorized administrators can manage records and monitor repository activity. The system aims to improve access to references, reduce manual searching, and support a more organized research workflow. Functional testing indicates that the repository can provide a practical and accessible solution for storing and retrieving Information Technology research materials.', 2025, '2025-04-15', 'repository, web, research', 1, 1, 'storage/manuscripts/research-20260918-07ae3eb4bf715cbb.pdf', 'approved', NULL, 42, 12, '2026-09-17 23:45:22', '2026-09-18 02:10:14'),
(2, 'Sample Mobile Student Companion', '[SAMPLE DATA - DEVELOPMENT ONLY] Fictional record used to test Study Vault.', 2024, '2024-08-20', 'mobile, student, application', 1, 1, NULL, 'approved', NULL, 18, 5, '2026-09-17 23:45:22', '2026-09-17 23:45:22'),
(3, 'Sample Network Access Security Study', '[SAMPLE DATA - DEVELOPMENT ONLY] Fictional record used to test Study Vault.', 2023, '2023-11-10', 'networking, security, access', 2, 1, NULL, 'approved', NULL, 25, 8, '2026-09-17 23:45:22', '2026-09-17 23:45:22'),
(4, 'Sample Pending Manuscript (awaiting review)', '[SAMPLE DATA - DEVELOPMENT ONLY] This record is PENDING, so students must not see it. Approve it from Admin > Research to publish it.', 2025, '2025-09-01', 'pending, sample, review', 1, 1, NULL, 'pending', NULL, 0, 0, '2026-10-01 09:00:00', '2026-10-01 09:00:00'),
(5, 'Sample Rejected Manuscript', '[SAMPLE DATA - DEVELOPMENT ONLY] This record is REJECTED, so students must not see it.', 2024, '2024-05-12', 'rejected, sample', 2, 1, NULL, 'rejected', NULL, 0, 0, '2026-10-01 09:05:00', '2026-10-01 09:05:00');

CREATE TABLE IF NOT EXISTS `research_authors` (
  `research_id` int NOT NULL,
  `author_id` int NOT NULL,
  PRIMARY KEY (`research_id`,`author_id`),
  KEY `fk_ra_author` (`author_id`),
  CONSTRAINT `fk_ra_author` FOREIGN KEY (`author_id`) REFERENCES `authors` (`author_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ra_research` FOREIGN KEY (`research_id`) REFERENCES `research` (`research_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `research_authors` (`research_id`, `author_id`) VALUES
(1, 1), (1, 2), (2, 2), (3, 3), (4, 1), (5, 3);

-- ---------------------------------------------------------------------------
-- Access plans and subscriptions
-- ---------------------------------------------------------------------------
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

INSERT INTO `access_plans` (`plan_id`, `plan_name`, `description`, `price`, `duration_days`, `full_access`, `status`, `sort_order`) VALUES
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

INSERT INTO `subscriptions` (`subscription_id`, `student_id`, `plan_id`, `status`, `reference_code`, `amount_paid`, `payment_mode`, `start_date`, `expiration_date`) VALUES
(1, 12, 2, 'active', 'SV-DEMO-0001', 99.00, 'demo', DATE_SUB(NOW(), INTERVAL 5 DAY),  DATE_ADD(NOW(), INTERVAL 25 DAY)),
(2, 13, 2, 'active', 'SV-DEMO-0002', 99.00, 'demo', DATE_SUB(NOW(), INTERVAL 33 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY));
-- Subscription 2 is still flagged "active" on purpose: the app recognises it as expired automatically.

-- ---------------------------------------------------------------------------
-- Activity log and login throttling
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int DEFAULT NULL,
  `student_id` int DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_log_date` (`created_at`),
  KEY `fk_log_admin` (`admin_id`),
  KEY `fk_log_student` (`student_id`),
  CONSTRAINT `fk_log_admin` FOREIGN KEY (`admin_id`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_log_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `activity_logs` (`log_id`, `admin_id`, `action`, `created_at`) VALUES
(1, 1, 'Initial database setup completed', '2026-09-17 23:45:22'),
(2, 1, 'Signed in', '2026-09-17 23:46:43'),
(3, 1, 'Signed in', '2026-10-02 16:45:49');

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `attempt_id` int NOT NULL AUTO_INCREMENT,
  `identifier` varchar(120) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attempt_id`),
  KEY `idx_attempt_identifier` (`identifier`,`attempted_at`),
  KEY `idx_attempt_ip` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
