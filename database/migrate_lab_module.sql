-- ============================================================
-- MEDCORE LABORATORY MODULE MIGRATION
-- Version: 2.0.0
-- Description: Adds full Laboratory Management System
-- Run after schema.sql
-- ============================================================

USE `medcore`;

-- ============================================================
-- STEP 1: Extend users.role ENUM to include LABORATORY
-- ============================================================
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('PATIENT','DOCTOR','HOSPITAL_ADMIN','SYSTEM_ADMIN','LABORATORY') NOT NULL;

-- ============================================================
-- TABLE L1: laboratories
-- Mirrors the 'doctors' table pattern under hospital ownership
-- ============================================================
CREATE TABLE IF NOT EXISTS `laboratories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL COMMENT 'Lab admin user account',
  `lab_uid` VARCHAR(20) NOT NULL COMMENT 'Format: LAB-000001',
  `name` VARCHAR(255) NOT NULL,
  `registration_number` VARCHAR(100) NOT NULL,
  `contact_phone` VARCHAR(20) DEFAULT NULL,
  `contact_email` VARCHAR(255) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `services` TEXT DEFAULT NULL COMMENT 'Comma-separated list of services offered',
  `logo_path` VARCHAR(500) DEFAULT NULL,
  `verification_status` ENUM('PENDING','VERIFIED','REJECTED','SUSPENDED') NOT NULL DEFAULT 'PENDING',
  `verified_by` INT UNSIGNED DEFAULT NULL COMMENT 'admin user_id',
  `verified_at` DATETIME DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lab_uid` (`lab_uid`),
  UNIQUE KEY `uq_lab_reg` (`registration_number`),
  INDEX `idx_lab_user` (`user_id`),
  INDEX `idx_lab_status` (`verification_status`),
  CONSTRAINT `fk_lab_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE L2: hospital_laboratories (Affiliation — mirrors doctor_hospitals)
-- ============================================================
CREATE TABLE IF NOT EXISTS `hospital_laboratories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `laboratory_id` INT UNSIGNED NOT NULL,
  `hospital_id` INT UNSIGNED NOT NULL,
  `status` ENUM('PENDING','APPROVED','REJECTED','SUSPENDED','REMOVED') NOT NULL DEFAULT 'PENDING',
  `approved_by` INT UNSIGNED DEFAULT NULL COMMENT 'hospital admin user_id',
  `approved_at` DATETIME DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lab_hospital` (`laboratory_id`, `hospital_id`),
  INDEX `idx_hl_lab` (`laboratory_id`),
  INDEX `idx_hl_hospital` (`hospital_id`),
  INDEX `idx_hl_status` (`status`),
  CONSTRAINT `fk_hl_lab` FOREIGN KEY (`laboratory_id`) REFERENCES `laboratories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hl_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE L3: prescription_lab_orders
-- Created when a doctor prescribes lab tests on a prescription
-- ============================================================
CREATE TABLE IF NOT EXISTS `prescription_lab_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lab_token` VARCHAR(12) NOT NULL COMMENT 'Short token e.g. LT-XK9F2 — searchable by lab staff',
  `prescription_id` INT UNSIGNED DEFAULT NULL COMMENT 'FK to prescriptions',
  `patient_id` INT UNSIGNED NOT NULL,
  `doctor_id` INT UNSIGNED NOT NULL,
  `hospital_id` INT UNSIGNED NOT NULL,
  `laboratory_id` INT UNSIGNED DEFAULT NULL COMMENT 'Set when a specific lab claims/processes this order',
  `test_notes` TEXT DEFAULT NULL COMMENT 'Special instructions from the doctor',
  `status` ENUM('PENDING','IN_PROGRESS','PARTIAL','COMPLETED','EXPIRED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `expires_at` DATETIME NOT NULL COMMENT 'Token validity — 30 days by default',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lab_token` (`lab_token`),
  INDEX `idx_plo_patient` (`patient_id`),
  INDEX `idx_plo_doctor` (`doctor_id`),
  INDEX `idx_plo_hospital` (`hospital_id`),
  INDEX `idx_plo_lab` (`laboratory_id`),
  INDEX `idx_plo_status` (`status`),
  INDEX `idx_plo_token` (`lab_token`),
  CONSTRAINT `fk_plo_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_plo_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `fk_plo_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`),
  CONSTRAINT `fk_plo_prescription` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plo_lab` FOREIGN KEY (`laboratory_id`) REFERENCES `laboratories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE L4: lab_order_tests
-- Individual tests within a lab order
-- ============================================================
CREATE TABLE IF NOT EXISTS `lab_order_tests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lab_order_id` INT UNSIGNED NOT NULL,
  `test_name` VARCHAR(255) NOT NULL COMMENT 'e.g. CBC, HbA1c, Chest X-Ray',
  `test_category` VARCHAR(100) DEFAULT NULL COMMENT 'e.g. Haematology, Radiology, Biochemistry',
  `urgency` ENUM('ROUTINE','URGENT','STAT') NOT NULL DEFAULT 'ROUTINE',
  `instructions` TEXT DEFAULT NULL COMMENT 'Specific instructions for this test',
  `status` ENUM('PENDING','IN_PROGRESS','COMPLETED') NOT NULL DEFAULT 'PENDING',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_lot_order` (`lab_order_id`),
  INDEX `idx_lot_status` (`status`),
  CONSTRAINT `fk_lot_order` FOREIGN KEY (`lab_order_id`) REFERENCES `prescription_lab_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE L5: lab_test_results
-- Result uploaded by the laboratory for a specific test
-- ============================================================
CREATE TABLE IF NOT EXISTS `lab_test_results` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lab_order_test_id` INT UNSIGNED NOT NULL,
  `laboratory_id` INT UNSIGNED NOT NULL COMMENT 'Which lab uploaded — for patient tracking',
  `hospital_id` INT UNSIGNED NOT NULL COMMENT 'Which hospital — for patient tracking',
  `result_summary` TEXT DEFAULT NULL COMMENT 'Text description of the result',
  `result_value` VARCHAR(255) DEFAULT NULL COMMENT 'Numeric or coded result if applicable',
  `reference_range` VARCHAR(255) DEFAULT NULL COMMENT 'e.g. 70-110 mg/dL',
  `result_status` ENUM('NORMAL','ABNORMAL','CRITICAL','PENDING_REVIEW') NOT NULL DEFAULT 'PENDING_REVIEW',
  `technician_name` VARCHAR(255) DEFAULT NULL,
  `uploaded_by` INT UNSIGNED NOT NULL COMMENT 'lab user_id',
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ltr_test` (`lab_order_test_id`),
  INDEX `idx_ltr_lab` (`laboratory_id`),
  INDEX `idx_ltr_hospital` (`hospital_id`),
  CONSTRAINT `fk_ltr_test` FOREIGN KEY (`lab_order_test_id`) REFERENCES `lab_order_tests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ltr_lab` FOREIGN KEY (`laboratory_id`) REFERENCES `laboratories` (`id`),
  CONSTRAINT `fk_ltr_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE L6: lab_result_files
-- One or more files per test result (PDF, images, etc.)
-- ============================================================
CREATE TABLE IF NOT EXISTS `lab_result_files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lab_test_result_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `original_name` VARCHAR(255) DEFAULT NULL,
  `file_type` ENUM('PDF','IMAGE','OTHER') NOT NULL DEFAULT 'OTHER',
  `file_size` INT UNSIGNED DEFAULT NULL COMMENT 'Size in bytes',
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_lrf_result` (`lab_test_result_id`),
  CONSTRAINT `fk_lrf_result` FOREIGN KEY (`lab_test_result_id`) REFERENCES `lab_test_results` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SUMMARY
-- ============================================================
-- Tables created: laboratories, hospital_laboratories,
--   prescription_lab_orders, lab_order_tests,
--   lab_test_results, lab_result_files
-- Altered: users.role ENUM
-- ============================================================
