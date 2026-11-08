<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';

try {
    $db = getDB();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $sql = "
        CREATE TABLE IF NOT EXISTS `patient_diagnostics` (
          `id` INTEGER PRIMARY KEY AUTOINCREMENT,
          `diagnostic_uid` VARCHAR(30) NOT NULL UNIQUE,
          `patient_id` INTEGER NOT NULL,
          `hospital_id` INTEGER NOT NULL,
          `ordered_by_doctor_id` INTEGER DEFAULT NULL,
          `diagnostic_category` VARCHAR(50) NOT NULL DEFAULT 'RADIOLOGY',
          `modality` VARCHAR(50) NOT NULL DEFAULT 'XRAY',
          `title` VARCHAR(255) NOT NULL,
          `body_part` VARCHAR(100) DEFAULT NULL,
          `clinical_indication` TEXT DEFAULT NULL,
          `findings` TEXT DEFAULT NULL,
          `impression` TEXT DEFAULT NULL,
          `test_date` DATE DEFAULT NULL,
          `report_date` DATE DEFAULT NULL,
          `reporting_specialist` VARCHAR(255) DEFAULT NULL,
          `status` VARCHAR(50) NOT NULL DEFAULT 'COMPLETED',
          `storage_type` VARCHAR(50) NOT NULL DEFAULT 'LOCAL_FILE',
          `file_path` VARCHAR(500) DEFAULT NULL,
          `file_name` VARCHAR(255) DEFAULT NULL,
          `file_type` VARCHAR(50) DEFAULT NULL,
          `file_size` INTEGER DEFAULT NULL,
          `drive_url` VARCHAR(1000) DEFAULT NULL,
          `verified_by` INTEGER DEFAULT NULL,
          `verified_at` DATETIME DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_diag_patient ON patient_diagnostics(patient_id);
        CREATE INDEX IF NOT EXISTS idx_diag_hospital ON patient_diagnostics(hospital_id);
        CREATE INDEX IF NOT EXISTS idx_diag_doctor ON patient_diagnostics(ordered_by_doctor_id);
        CREATE INDEX IF NOT EXISTS idx_diag_modality ON patient_diagnostics(modality);
        CREATE INDEX IF NOT EXISTS idx_diag_status ON patient_diagnostics(status);
        ";
    } else {
        $sql = "
        CREATE TABLE IF NOT EXISTS `patient_diagnostics` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `diagnostic_uid` VARCHAR(30) NOT NULL,
          `patient_id` INT UNSIGNED NOT NULL,
          `hospital_id` INT UNSIGNED NOT NULL,
          `ordered_by_doctor_id` INT UNSIGNED DEFAULT NULL,
          `diagnostic_category` ENUM('RADIOLOGY','PATHOLOGY','CARDIOLOGY','OTHER') NOT NULL DEFAULT 'RADIOLOGY',
          `modality` ENUM('XRAY','MRI','CT_SCAN','ULTRASOUND','ECG','ECHOCARDIOGRAM','ENDOSCOPY','BLOOD_TEST','URINE_TEST','BIOPSY','HISTOPATHOLOGY','OTHER') NOT NULL DEFAULT 'XRAY',
          `title` VARCHAR(255) NOT NULL,
          `body_part` VARCHAR(100) DEFAULT NULL,
          `clinical_indication` TEXT DEFAULT NULL,
          `findings` TEXT DEFAULT NULL,
          `impression` TEXT DEFAULT NULL,
          `test_date` DATE DEFAULT NULL,
          `report_date` DATE DEFAULT NULL,
          `reporting_specialist` VARCHAR(255) DEFAULT NULL,
          `status` ENUM('ORDERED','IN_PROGRESS','COMPLETED','VERIFIED') NOT NULL DEFAULT 'COMPLETED',
          `storage_type` ENUM('LOCAL_FILE','DRIVE_URL','BOTH') NOT NULL DEFAULT 'LOCAL_FILE',
          `file_path` VARCHAR(500) DEFAULT NULL,
          `file_name` VARCHAR(255) DEFAULT NULL,
          `file_type` VARCHAR(50) DEFAULT NULL,
          `file_size` INT UNSIGNED DEFAULT NULL,
          `drive_url` VARCHAR(1000) DEFAULT NULL,
          `verified_by` INT UNSIGNED DEFAULT NULL,
          `verified_at` DATETIME DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_diag_uid` (`diagnostic_uid`),
          INDEX `idx_diag_patient` (`patient_id`),
          INDEX `idx_diag_hospital` (`hospital_id`),
          INDEX `idx_diag_doctor` (`ordered_by_doctor_id`),
          INDEX `idx_diag_modality` (`modality`),
          INDEX `idx_diag_status` (`status`),
          CONSTRAINT `fk_pdiag_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_pdiag_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_pdiag_doctor` FOREIGN KEY (`ordered_by_doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }
    $db->exec($sql);
    echo "TABLE patient_diagnostics CREATED SUCCESSFULLY\n";

    // Also ensure upload directories exist
    $uploadDir = __DIR__ . '/../public/uploads/diagnostics';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
        echo "Upload directory created: {$uploadDir}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
