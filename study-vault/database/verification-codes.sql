-- ============================================================================
-- Study Vault: Email + 6-digit code verification
-- Run AFTER database/school-db.sql. Safe to run more than once.
-- Requires MariaDB (XAMPP default) for the "IF NOT EXISTS" column syntax.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `verification_codes` (
  `id`               int NOT NULL AUTO_INCREMENT,
  `school_record_id` int NOT NULL,
  `student_id`       varchar(50)  NOT NULL,
  `email`            varchar(120) NOT NULL,
  `code`             varchar(6)   NOT NULL,
  `attempts`         int          NOT NULL DEFAULT 0,
  `used`             tinyint(1)   NOT NULL DEFAULT 0,
  `expires_at`       datetime     NOT NULL,
  `created_at`       datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vc_school_record` (`school_record_id`),
  KEY `idx_vc_code` (`code`),
  KEY `idx_vc_expires` (`expires_at`),
  CONSTRAINT `fk_vc_school_record` FOREIGN KEY (`school_record_id`)
    REFERENCES `school_student_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Two columns the students table needs so an account can be linked to its school record.
ALTER TABLE `students`
  ADD COLUMN IF NOT EXISTS `school_record_id` int DEFAULT NULL AFTER `program_id`,
  ADD COLUMN IF NOT EXISTS `verification_status` enum('unverified','verified') NOT NULL DEFAULT 'unverified' AFTER `status`;

-- One school record can be linked to only one account (NULLs are allowed many times).
ALTER TABLE `students`
  ADD UNIQUE INDEX IF NOT EXISTS `uq_students_school_record` (`school_record_id`);
