-- ============================================================================
-- Study Vault: MOCK school database (simulated registrar records)
-- Import in phpMyAdmin (database: study_vault). Safe to run more than once.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `school_student_records` (
  `id`              int NOT NULL AUTO_INCREMENT,
  `student_id`      varchar(50)  NOT NULL,
  `full_name`       varchar(160) NOT NULL,
  `email`           varchar(120) DEFAULT NULL,
  `program`         varchar(150) NOT NULL,
  `status`          enum('enrolled','alumni','inactive') NOT NULL DEFAULT 'enrolled',
  `academic_year`   varchar(20)  DEFAULT NULL,
  `graduation_year` smallint     DEFAULT NULL,
  `created_at`      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_school_student_id` (`student_id`),
  KEY `idx_school_status` (`status`),
  KEY `idx_school_program` (`program`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed rows (each is inserted only if that Student ID is not there yet).
INSERT INTO `school_student_records` (`student_id`,`full_name`,`email`,`program`,`status`,`academic_year`,`graduation_year`)
SELECT * FROM (SELECT '24-01048' AS a,'Johnbrix Gantala' AS b,'gantalajohnbrix12@gmail.com' AS c,'BSIT' AS d,'enrolled' AS e,'2025-2026' AS f,NULL AS g) t
WHERE NOT EXISTS (SELECT 1 FROM `school_student_records` WHERE `student_id`='24-01048');

INSERT INTO `school_student_records` (`student_id`,`full_name`,`email`,`program`,`status`,`academic_year`,`graduation_year`)
SELECT * FROM (SELECT '24-01081','Aljon Peralta','aljonperalta2208@gmail.com','BSIT','enrolled','2025-2026',NULL) t
WHERE NOT EXISTS (SELECT 1 FROM `school_student_records` WHERE `student_id`='2022-0001');
