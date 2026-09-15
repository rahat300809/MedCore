-- ============================================================
-- MEDCORE DATABASE SCHEMA
-- Version: 1.0.0
-- Charset: utf8mb4
-- Engine: InnoDB
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+06:00";

CREATE DATABASE IF NOT EXISTS `medcore`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `medcore`;

-- ============================================================
-- TABLE 1: users
-- ============================================================
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role` ENUM('PATIENT','DOCTOR','HOSPITAL_ADMIN','SYSTEM_ADMIN') NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `status` ENUM('ACTIVE','PENDING','SUSPENDED','BLOCKED') NOT NULL DEFAULT 'PENDING',
  `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `phone_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 2: patients
-- ============================================================
CREATE TABLE `patients` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `patient_uid` VARCHAR(20) NOT NULL COMMENT 'Format: PT-000001',
  `nid_encrypted` TEXT NOT NULL COMMENT 'AES-256 encrypted NID',
  `nid_lookup_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash for lookup',
  `nid_last4` CHAR(4) NOT NULL COMMENT 'Last 4 digits for display',
  `full_name` VARCHAR(255) NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `gender` ENUM('MALE','FEMALE','OTHER') NOT NULL,
  `blood_group` ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','UNKNOWN') DEFAULT 'UNKNOWN',
  `address` TEXT DEFAULT NULL,
  `emergency_contact_name` VARCHAR(255) DEFAULT NULL,
  `emergency_contact_phone` VARCHAR(20) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_patients_uid` (`patient_uid`),
  UNIQUE KEY `uq_patients_nid_hash` (`nid_lookup_hash`),
  INDEX `idx_patients_user` (`user_id`),
  INDEX `idx_patients_name` (`full_name`),
  CONSTRAINT `fk_patients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 3: doctors
-- ============================================================
CREATE TABLE `doctors` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `doctor_uid` VARCHAR(20) NOT NULL COMMENT 'Format: DR-000001',
  `medical_registration_id` VARCHAR(100) NOT NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `specialization` VARCHAR(255) DEFAULT NULL,
  `qualification` TEXT DEFAULT NULL,
  `designation` VARCHAR(255) DEFAULT NULL,
  `experience_years` TINYINT UNSIGNED DEFAULT 0,
  `profile_image` VARCHAR(500) DEFAULT NULL,
  `verification_status` ENUM('PENDING','VERIFIED','REJECTED','SUSPENDED') NOT NULL DEFAULT 'PENDING',
  `verified_by` INT UNSIGNED DEFAULT NULL COMMENT 'admin user_id',
  `verified_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doctors_uid` (`doctor_uid`),
  UNIQUE KEY `uq_doctors_reg` (`medical_registration_id`),
  INDEX `idx_doctors_user` (`user_id`),
  INDEX `idx_doctors_status` (`verification_status`),
  CONSTRAINT `fk_doctors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 4: hospitals
-- ============================================================
CREATE TABLE `hospitals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL COMMENT 'Hospital admin user',
  `hospital_uid` VARCHAR(20) NOT NULL COMMENT 'Format: HOSP-000001',
  `name` VARCHAR(255) NOT NULL,
  `registration_number` VARCHAR(100) NOT NULL,
  `type` ENUM('GENERAL','SPECIALIZED','CLINIC','DIAGNOSTIC','TEACHING','OTHER') DEFAULT 'GENERAL',
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `district` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `website` VARCHAR(500) DEFAULT NULL,
  `logo_path` VARCHAR(500) DEFAULT NULL,
  `verification_status` ENUM('PENDING','VERIFIED','REJECTED','SUSPENDED') NOT NULL DEFAULT 'PENDING',
  `verified_by` INT UNSIGNED DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hospitals_uid` (`hospital_uid`),
  UNIQUE KEY `uq_hospitals_reg` (`registration_number`),
  INDEX `idx_hospitals_status` (`verification_status`),
  INDEX `idx_hospitals_city` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 5: departments
-- ============================================================
CREATE TABLE `departments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hospital_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_dept_hospital` (`hospital_id`),
  CONSTRAINT `fk_dept_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 6: doctor_hospitals (AFFILIATION BACKBONE)
-- ============================================================
CREATE TABLE `doctor_hospitals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` INT UNSIGNED NOT NULL,
  `hospital_id` INT UNSIGNED NOT NULL,
  `department_id` INT UNSIGNED DEFAULT NULL,
  `designation` VARCHAR(255) DEFAULT NULL,
  `employment_type` ENUM('FULL_TIME','PART_TIME','VISITING','HONORARY') DEFAULT 'FULL_TIME',
  `status` ENUM('PENDING','APPROVED','REJECTED','SUSPENDED','REMOVED') NOT NULL DEFAULT 'PENDING',
  `joined_at` DATE DEFAULT NULL,
  `left_at` DATE DEFAULT NULL,
  `approved_by` INT UNSIGNED DEFAULT NULL COMMENT 'hospital admin user_id',
  `approved_at` DATETIME DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doctor_hospital` (`doctor_id`, `hospital_id`),
  INDEX `idx_dh_doctor` (`doctor_id`),
  INDEX `idx_dh_hospital` (`hospital_id`),
  INDEX `idx_dh_status` (`status`),
  CONSTRAINT `fk_dh_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dh_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dh_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 7: doctor_documents
-- ============================================================
CREATE TABLE `doctor_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` INT UNSIGNED NOT NULL,
  `document_type` ENUM('MEDICAL_DEGREE','REGISTRATION_CERTIFICATE','SPECIALIZATION_CERT','NID','PHOTO','OTHER') NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `original_name` VARCHAR(255) DEFAULT NULL,
  `file_size` INT UNSIGNED DEFAULT NULL,
  `verification_status` ENUM('PENDING','VERIFIED','REJECTED') DEFAULT 'PENDING',
  `verified_by` INT UNSIGNED DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_doc_doctor` (`doctor_id`),
  CONSTRAINT `fk_doc_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 8: hospital_documents
-- ============================================================
CREATE TABLE `hospital_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hospital_id` INT UNSIGNED NOT NULL,
  `document_type` ENUM('REGISTRATION_CERT','LICENSE','ACCREDITATION','TAX_CERT','OTHER') NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `original_name` VARCHAR(255) DEFAULT NULL,
  `verification_status` ENUM('PENDING','VERIFIED','REJECTED') DEFAULT 'PENDING',
  `verified_by` INT UNSIGNED DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_hdoc_hospital` (`hospital_id`),
  CONSTRAINT `fk_hdoc_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 9: medical_histories
-- ============================================================
CREATE TABLE `medical_histories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` INT UNSIGNED NOT NULL,
  `condition_name` VARCHAR(255) NOT NULL,
  `icd_code` VARCHAR(20) DEFAULT NULL COMMENT 'ICD-11 code',
  `description` TEXT DEFAULT NULL,
  `diagnosed_date` DATE DEFAULT NULL,
  `status` ENUM('ACTIVE','RESOLVED','CHRONIC','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL COMMENT 'doctor who added',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_mh_patient` (`patient_id`),
  CONSTRAINT `fk_mh_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 10: allergies (reference table)
-- ============================================================
CREATE TABLE `allergies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `type` ENUM('DRUG','FOOD','ENVIRONMENTAL','INSECT','LATEX','OTHER') NOT NULL DEFAULT 'OTHER',
  `description` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_allergy_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 11: patient_allergies
-- ============================================================
CREATE TABLE `patient_allergies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` INT UNSIGNED NOT NULL,
  `allergy_id` INT UNSIGNED NOT NULL,
  `severity` ENUM('MILD','MODERATE','SEVERE','LIFE_THREATENING') DEFAULT 'MILD',
  `reaction` TEXT DEFAULT NULL,
  `verified` TINYINT(1) NOT NULL DEFAULT 0,
  `added_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_patient_allergy` (`patient_id`, `allergy_id`),
  INDEX `idx_pa_patient` (`patient_id`),
  CONSTRAINT `fk_pa_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pa_allergy` FOREIGN KEY (`allergy_id`) REFERENCES `allergies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 12: medications (catalog)
-- ============================================================
CREATE TABLE `medications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `generic_name` VARCHAR(255) NOT NULL,
  `brand_name` VARCHAR(255) DEFAULT NULL,
  `strength` VARCHAR(100) DEFAULT NULL,
  `dosage_form` ENUM('TABLET','CAPSULE','SYRUP','INJECTION','INHALER','CREAM','DROP','SUPPOSITORY','OTHER') DEFAULT 'TABLET',
  `drug_class` VARCHAR(255) DEFAULT NULL,
  `manufacturer` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `contraindications` TEXT DEFAULT NULL,
  `status` ENUM('ACTIVE','DISCONTINUED') DEFAULT 'ACTIVE',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_med_generic` (`generic_name`),
  FULLTEXT KEY `ft_medications` (`generic_name`, `brand_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 13: patient_medications
-- ============================================================
CREATE TABLE `patient_medications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` INT UNSIGNED NOT NULL,
  `medication_id` INT UNSIGNED NOT NULL,
  `dosage` VARCHAR(100) DEFAULT NULL,
  `frequency` VARCHAR(100) DEFAULT NULL,
  `route` ENUM('ORAL','INJECTION','TOPICAL','INHALED','RECTAL','SUBLINGUAL','OTHER') DEFAULT 'ORAL',
  `started_at` DATE DEFAULT NULL,
  `ended_at` DATE DEFAULT NULL,
  `status` ENUM('ACTIVE','COMPLETED','DISCONTINUED') DEFAULT 'ACTIVE',
  `prescribed_by` INT UNSIGNED DEFAULT NULL COMMENT 'doctor_id',
  `prescription_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pm_patient` (`patient_id`),
  INDEX `idx_pm_status` (`status`),
  CONSTRAINT `fk_pm_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pm_med` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 14: lab_reports
-- ============================================================
CREATE TABLE `lab_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` INT UNSIGNED NOT NULL,
  `report_name` VARCHAR(255) NOT NULL,
  `test_date` DATE DEFAULT NULL,
  `laboratory_name` VARCHAR(255) DEFAULT NULL,
  `result_summary` TEXT DEFAULT NULL,
  `file_path` VARCHAR(500) DEFAULT NULL,
  `uploaded_by` INT UNSIGNED DEFAULT NULL COMMENT 'user_id who uploaded',
  `verified` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_lab_patient` (`patient_id`),
  CONSTRAINT `fk_lab_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 15: consultations
-- ============================================================
CREATE TABLE `consultations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `consultation_uid` VARCHAR(30) NOT NULL COMMENT 'Format: CONS-20260916-0001',
  `patient_id` INT UNSIGNED NOT NULL,
  `doctor_id` INT UNSIGNED NOT NULL,
  `hospital_id` INT UNSIGNED NOT NULL,
  `department_id` INT UNSIGNED DEFAULT NULL,
  `consultation_type` ENUM('IN_PERSON','TELEHEALTH','FOLLOW_UP','EMERGENCY') DEFAULT 'IN_PERSON',
  `chief_complaint` TEXT DEFAULT NULL,
  `symptoms` TEXT DEFAULT NULL,
  `clinical_notes` TEXT DEFAULT NULL,
  `diagnosis_summary` TEXT DEFAULT NULL,
  `investigation` TEXT DEFAULT NULL,
  `advice` TEXT DEFAULT NULL,
  `follow_up_date` DATE DEFAULT NULL,
  `consultation_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cons_uid` (`consultation_uid`),
  INDEX `idx_cons_patient` (`patient_id`),
  INDEX `idx_cons_doctor` (`doctor_id`),
  INDEX `idx_cons_hospital` (`hospital_id`),
  INDEX `idx_cons_date` (`consultation_date`),
  CONSTRAINT `fk_cons_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_cons_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `fk_cons_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`),
  CONSTRAINT `fk_cons_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 16: diagnoses
-- ============================================================
CREATE TABLE `diagnoses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `consultation_id` INT UNSIGNED NOT NULL,
  `diagnosis_name` VARCHAR(255) NOT NULL,
  `diagnosis_code` VARCHAR(20) DEFAULT NULL COMMENT 'ICD-11',
  `diagnosis_type` ENUM('PRIMARY','SECONDARY','DIFFERENTIAL') DEFAULT 'PRIMARY',
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_diag_cons` (`consultation_id`),
  CONSTRAINT `fk_diag_cons` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 17: prescriptions
-- ============================================================
CREATE TABLE `prescriptions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `prescription_uid` VARCHAR(30) NOT NULL COMMENT 'Format: RX-20260916-0001',
  `consultation_id` INT UNSIGNED DEFAULT NULL,
  `patient_id` INT UNSIGNED NOT NULL,
  `doctor_id` INT UNSIGNED NOT NULL,
  `hospital_id` INT UNSIGNED NOT NULL,
  `diagnosis` TEXT DEFAULT NULL,
  `advice` TEXT DEFAULT NULL,
  `follow_up_date` DATE DEFAULT NULL,
  `status` ENUM('ACTIVE','COMPLETED','CANCELLED') DEFAULT 'ACTIVE',
  `doctor_signature` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rx_uid` (`prescription_uid`),
  INDEX `idx_rx_patient` (`patient_id`),
  INDEX `idx_rx_doctor` (`doctor_id`),
  INDEX `idx_rx_hospital` (`hospital_id`),
  INDEX `idx_rx_created` (`created_at`),
  CONSTRAINT `fk_rx_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_rx_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `fk_rx_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`),
  CONSTRAINT `fk_rx_cons` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 18: prescription_medicines
-- ============================================================
CREATE TABLE `prescription_medicines` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `prescription_id` INT UNSIGNED NOT NULL,
  `medication_id` INT UNSIGNED DEFAULT NULL,
  `medicine_name` VARCHAR(255) NOT NULL COMMENT 'Free text fallback',
  `strength` VARCHAR(100) DEFAULT NULL,
  `dosage` VARCHAR(100) DEFAULT NULL,
  `frequency` VARCHAR(100) DEFAULT NULL,
  `duration` VARCHAR(100) DEFAULT NULL,
  `route` ENUM('ORAL','INJECTION','TOPICAL','INHALED','RECTAL','SUBLINGUAL','OTHER') DEFAULT 'ORAL',
  `instructions` TEXT DEFAULT NULL,
  `quantity` VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_pm_prescription` (`prescription_id`),
  CONSTRAINT `fk_pm_prescription` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pm_medication` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 19: otp_verifications
-- ============================================================
CREATE TABLE `otp_verifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `reference_id` VARCHAR(100) DEFAULT NULL COMMENT 'For non-user OTPs (e.g. access_request_id)',
  `purpose` ENUM('PATIENT_REGISTRATION','LOGIN','MEDICAL_ACCESS','PASSWORD_RESET','HOSPITAL_LOGIN','DOCTOR_LOGIN') NOT NULL,
  `otp_hash` VARCHAR(255) NOT NULL,
  `otp_display` VARCHAR(6) DEFAULT NULL COMMENT 'For dev mode ONLY — remove in production',
  `channel` ENUM('EMAIL','SMS','BOTH') DEFAULT 'EMAIL',
  `destination_masked` VARCHAR(100) DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `attempt_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `status` ENUM('PENDING','VERIFIED','EXPIRED','EXHAUSTED') DEFAULT 'PENDING',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_otp_user` (`user_id`),
  INDEX `idx_otp_purpose` (`purpose`),
  INDEX `idx_otp_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 20: access_requests
-- ============================================================
CREATE TABLE `access_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` INT UNSIGNED NOT NULL,
  `doctor_id` INT UNSIGNED NOT NULL,
  `hospital_id` INT UNSIGNED NOT NULL,
  `request_type` SET('MEDICAL_HISTORY','PRESCRIPTIONS','MEDICATIONS','ALLERGIES','LAB_REPORTS','ALL') DEFAULT 'ALL',
  `status` ENUM('PENDING','APPROVED','DENIED','EXPIRED','CANCELLED') DEFAULT 'PENDING',
  `otp_verification_id` INT UNSIGNED DEFAULT NULL,
  `patient_message` TEXT DEFAULT NULL,
  `doctor_notes` TEXT DEFAULT NULL,
  `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_ar_patient` (`patient_id`),
  INDEX `idx_ar_doctor` (`doctor_id`),
  INDEX `idx_ar_status` (`status`),
  CONSTRAINT `fk_ar_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_ar_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `fk_ar_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 21: access_sessions
-- ============================================================
CREATE TABLE `access_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `access_request_id` INT UNSIGNED NOT NULL,
  `patient_id` INT UNSIGNED NOT NULL,
  `doctor_id` INT UNSIGNED NOT NULL,
  `hospital_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL,
  `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  `revoked_at` DATETIME DEFAULT NULL,
  `status` ENUM('ACTIVE','EXPIRED','REVOKED') DEFAULT 'ACTIVE',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_as_token` (`token_hash`),
  INDEX `idx_as_doctor_patient` (`doctor_id`, `patient_id`),
  INDEX `idx_as_status` (`status`),
  INDEX `idx_as_expires` (`expires_at`),
  CONSTRAINT `fk_as_request` FOREIGN KEY (`access_request_id`) REFERENCES `access_requests` (`id`),
  CONSTRAINT `fk_as_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_as_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `fk_as_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 22: notifications
-- ============================================================
CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` INT UNSIGNED DEFAULT NULL,
  `action_url` VARCHAR(500) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_notif_user` (`user_id`),
  INDEX `idx_notif_read` (`is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 23: audit_logs
-- ============================================================
CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_type` VARCHAR(50) DEFAULT NULL,
  `target_id` INT UNSIGNED DEFAULT NULL,
  `patient_id` INT UNSIGNED DEFAULT NULL,
  `doctor_id` INT UNSIGNED DEFAULT NULL,
  `hospital_id` INT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `metadata` JSON DEFAULT NULL,
  `severity` ENUM('INFO','WARNING','CRITICAL') DEFAULT 'INFO',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_audit_user` (`user_id`),
  INDEX `idx_audit_action` (`action`),
  INDEX `idx_audit_patient` (`patient_id`),
  INDEX `idx_audit_hospital` (`hospital_id`),
  INDEX `idx_audit_created` (`created_at`),
  INDEX `idx_audit_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
