-- ============================================================
-- MEDCORE SEED DATA
-- Demo accounts + sample healthcare data
-- ============================================================

USE `medcore`;

-- ============================================================
-- USERS (passwords: all use 'Demo123!' hashed with bcrypt)
-- password_hash for 'Demo123!'
-- ============================================================
INSERT INTO `users` (`role`, `email`, `phone`, `password_hash`, `status`, `email_verified`, `phone_verified`) VALUES
-- System Admin
('SYSTEM_ADMIN', 'admin@medcore.local', '01700000000', '$2y$10$ZGg7dwO7DCf6ablxX4THTunIvPEH8n1HUTUex0cInoPC7a390xmm6', 'ACTIVE', 1, 1),
-- Hospital Admins
('HOSPITAL_ADMIN', 'admin@abc-hospital.com', '01711111111', '$2y$10$ZGg7dwO7DCf6ablxX4THTunIvPEH8n1HUTUex0cInoPC7a390xmm6', 'ACTIVE', 1, 1),
('HOSPITAL_ADMIN', 'admin@xyz-medical.com', '01722222222', '$2y$10$ZGg7dwO7DCf6ablxX4THTunIvPEH8n1HUTUex0cInoPC7a390xmm6', 'ACTIVE', 1, 1),
('HOSPITAL_ADMIN', 'admin@dhaka-care.com', '01733333333', '$2y$10$ZGg7dwO7DCf6ablxX4THTunIvPEH8n1HUTUex0cInoPC7a390xmm6', 'ACTIVE', 1, 1),
-- Doctors
('DOCTOR', 'dr.rahman@medcore.local', '01755555555', '$2y$10$ZGg7dwO7DCf6ablxX4THTunIvPEH8n1HUTUex0cInoPC7a390xmm6', 'ACTIVE', 1, 1),
('DOCTOR', 'dr.karim@medcore.local', '01766666666', '$2y$10$ZGg7dwO7DCf6ablxX4THTunIvPEH8n1HUTUex0cInoPC7a390xmm6', 'ACTIVE', 1, 1),
('DOCTOR', 'dr.hasan@medcore.local', '01777777777', '$2y$10$ZGg7dwO7DCf6ablxX4THTunIvPEH8n1HUTUex0cInoPC7a390xmm6', 'ACTIVE', 1, 1),
-- Patients
('PATIENT', 'rahat@example.com', '01788888888', '$2y$10$ZGg7dwO7DCf6ablxX4THTunIvPEH8n1HUTUex0cInoPC7a390xmm6', 'ACTIVE', 1, 1),
('PATIENT', 'nasrin@example.com', '01799999999', '$2y$10$ZGg7dwO7DCf6ablxX4THTunIvPEH8n1HUTUex0cInoPC7a390xmm6', 'ACTIVE', 1, 1),
('PATIENT', 'kamal@example.com', '01800000001', '$2y$10$ZGg7dwO7DCf6ablxX4THTunIvPEH8n1HUTUex0cInoPC7a390xmm6', 'ACTIVE', 1, 1);

-- NOTE: password_hash above is bcrypt of 'password' (Laravel default dummy)
-- REAL password for demo: 'Demo123!' - update with actual hash after running:
-- php -r "echo password_hash('Demo123!', PASSWORD_BCRYPT);"

-- ============================================================
-- HOSPITALS
-- ============================================================
INSERT INTO `hospitals` (`user_id`, `hospital_uid`, `name`, `registration_number`, `type`, `address`, `city`, `district`, `phone`, `email`, `verification_status`, `verified_by`, `verified_at`) VALUES
(2, 'HOSP-000001', 'ABC General Hospital', 'DGDA-HOSP-2019-0001', 'GENERAL', 'House 15, Road 7, Dhanmondi', 'Dhaka', 'Dhaka', '02-9876543', 'admin@abc-hospital.com', 'VERIFIED', 1, '2026-01-15 10:00:00'),
(3, 'HOSP-000002', 'XYZ Medical Center', 'DGDA-HOSP-2020-0042', 'SPECIALIZED', 'Plot 22, Block C, Gulshan-1', 'Dhaka', 'Dhaka', '02-8765432', 'admin@xyz-medical.com', 'VERIFIED', 1, '2026-02-20 11:00:00'),
(4, 'HOSP-000003', 'Dhaka Care Hospital', 'DGDA-HOSP-2021-0087', 'GENERAL', '45 Shyamoli, Ring Road', 'Dhaka', 'Dhaka', '02-7654321', 'admin@dhaka-care.com', 'VERIFIED', 1, '2026-03-10 09:00:00');

-- ============================================================
-- DEPARTMENTS
-- ============================================================
INSERT INTO `departments` (`hospital_id`, `name`, `description`, `status`) VALUES
-- ABC Hospital
(1, 'Internal Medicine', 'General internal medicine and chronic disease management', 'ACTIVE'),
(1, 'Cardiology', 'Heart and cardiovascular system diseases', 'ACTIVE'),
(1, 'Neurology', 'Brain and nervous system disorders', 'ACTIVE'),
(1, 'Gastroenterology', 'Digestive system diseases', 'ACTIVE'),
(1, 'Emergency Medicine', '24/7 emergency care', 'ACTIVE'),
-- XYZ Medical
(2, 'Orthopedics', 'Bone and joint conditions', 'ACTIVE'),
(2, 'Dermatology', 'Skin conditions and disorders', 'ACTIVE'),
(2, 'Pediatrics', 'Child healthcare', 'ACTIVE'),
-- Dhaka Care
(3, 'Internal Medicine', 'General internal medicine', 'ACTIVE'),
(3, 'Pulmonology', 'Lung and respiratory conditions', 'ACTIVE');

-- ============================================================
-- DOCTORS
-- ============================================================
INSERT INTO `doctors` (`user_id`, `doctor_uid`, `medical_registration_id`, `full_name`, `specialization`, `qualification`, `designation`, `experience_years`, `verification_status`, `verified_by`, `verified_at`) VALUES
(5, 'DR-000001', 'BMDC-A-12345', 'Dr. Farhana Rahman', 'Internal Medicine', 'MBBS, FCPS (Medicine)', 'Senior Consultant', 12, 'VERIFIED', 1, '2026-01-20 10:00:00'),
(6, 'DR-000002', 'BMDC-A-67890', 'Dr. Kamrul Karim', 'Cardiology', 'MBBS, MD (Cardiology)', 'Consultant Cardiologist', 8, 'VERIFIED', 1, '2026-02-15 11:00:00'),
(7, 'DR-000003', 'BMDC-A-24680', 'Dr. Masum Hasan', 'Gastroenterology', 'MBBS, FCPS (Gastro)', 'Associate Consultant', 5, 'VERIFIED', 1, '2026-03-05 09:00:00');

-- ============================================================
-- DOCTOR-HOSPITAL AFFILIATIONS (THE BACKBONE)
-- ============================================================
INSERT INTO `doctor_hospitals` (`doctor_id`, `hospital_id`, `department_id`, `designation`, `employment_type`, `status`, `joined_at`, `approved_by`, `approved_at`) VALUES
-- Dr. Rahman → ABC Hospital (Internal Medicine) - APPROVED
(1, 1, 1, 'Senior Consultant', 'FULL_TIME', 'APPROVED', '2026-01-25', 2, '2026-01-25 10:00:00'),
-- Dr. Karim → ABC Hospital (Cardiology) - APPROVED
(2, 1, 2, 'Consultant', 'FULL_TIME', 'APPROVED', '2026-02-20', 2, '2026-02-20 11:00:00'),
-- Dr. Karim → XYZ Medical (Orthopedics) - APPROVED (visiting)
(2, 2, 6, 'Visiting Consultant', 'VISITING', 'APPROVED', '2026-03-01', 3, '2026-03-01 09:00:00'),
-- Dr. Hasan → ABC Hospital (Gastro) - APPROVED
(3, 1, 4, 'Associate Consultant', 'FULL_TIME', 'APPROVED', '2026-03-10', 2, '2026-03-10 10:00:00'),
-- Dr. Hasan → Dhaka Care (Internal Med) - PENDING
(3, 3, 9, 'Consultant', 'PART_TIME', 'PENDING', NULL, NULL, NULL);

-- ============================================================
-- PATIENTS
-- ============================================================
INSERT INTO `patients` (`user_id`, `patient_uid`, `nid_encrypted`, `nid_lookup_hash`, `nid_last4`, `full_name`, `date_of_birth`, `gender`, `blood_group`, `address`, `emergency_contact_name`, `emergency_contact_phone`) VALUES
(8, 'PT-000001', 'ENCRYPTED_NID_PLACEHOLDER_1', SHA2('1234567890123', 256), '0123', 'Rahat Mahamud', '1995-06-15', 'MALE', 'O+', 'House 12, Road 5, Mirpur-2, Dhaka', 'Rahim Mahamud', '01888888889'),
(9, 'PT-000002', 'ENCRYPTED_NID_PLACEHOLDER_2', SHA2('9876543210987', 256), '0987', 'Nasrin Begum', '1988-03-22', 'FEMALE', 'A+', 'Flat 3B, House 45, Uttara Sector 7', 'Karim Hossain', '01899999990'),
(10, 'PT-000003', 'ENCRYPTED_NID_PLACEHOLDER_3', SHA2('5555666677778', 256), '7778', 'Kamal Hossain', '1975-11-08', 'MALE', 'B+', 'Village: Rajnagar, Upazila: Singair, Manikganj', 'Jamal Hossain', '01800000002');

-- ============================================================
-- ALLERGIES REFERENCE
-- ============================================================
INSERT INTO `allergies` (`name`, `type`, `description`) VALUES
('Penicillin', 'DRUG', 'Beta-lactam antibiotic - may cause severe anaphylaxis'),
('Aspirin', 'DRUG', 'NSAID - may cause respiratory reactions'),
('Sulfonamides', 'DRUG', 'Sulfa drugs'),
('Seafood', 'FOOD', 'Shellfish and seafood allergy'),
('Peanuts', 'FOOD', 'Legume allergy'),
('Dust Mites', 'ENVIRONMENTAL', 'House dust mite allergy'),
('Latex', 'LATEX', 'Natural rubber latex'),
('Amoxicillin', 'DRUG', 'Beta-lactam antibiotic - cross-reactive with Penicillin');

-- ============================================================
-- PATIENT ALLERGIES
-- ============================================================
INSERT INTO `patient_allergies` (`patient_id`, `allergy_id`, `severity`, `reaction`, `verified`) VALUES
(1, 1, 'SEVERE', 'Anaphylaxis, hives, difficulty breathing - documented in 2022', 1),
(1, 8, 'SEVERE', 'Cross-reactivity with Penicillin - avoid all beta-lactams', 1),
(2, 4, 'MODERATE', 'Urticaria and mild swelling after shellfish consumption', 1),
(3, 2, 'MILD', 'Mild GI upset', 0);

-- ============================================================
-- MEDICATIONS CATALOG
-- ============================================================
INSERT INTO `medications` (`generic_name`, `brand_name`, `strength`, `dosage_form`, `drug_class`, `manufacturer`) VALUES
('Metformin', 'Glucophage', '500mg', 'TABLET', 'Biguanide antidiabetic', 'Merck'),
('Amlodipine', 'Norvasc', '5mg', 'TABLET', 'Calcium channel blocker', 'Pfizer'),
('Atorvastatin', 'Lipitor', '20mg', 'TABLET', 'Statin', 'Pfizer'),
('Omeprazole', 'Losec', '20mg', 'CAPSULE', 'Proton pump inhibitor', 'AstraZeneca'),
('Azithromycin', 'Zithromax', '500mg', 'TABLET', 'Macrolide antibiotic', 'Pfizer'),
('Paracetamol', 'Napa', '500mg', 'TABLET', 'Analgesic/Antipyretic', 'Beximco'),
('Cetirizine', 'Zyrtec', '10mg', 'TABLET', 'Antihistamine', 'GSK'),
('Pantoprazole', 'Controloc', '40mg', 'TABLET', 'Proton pump inhibitor', 'Nycomed'),
('Rosuvastatin', 'Crestor', '10mg', 'TABLET', 'Statin', 'AstraZeneca'),
('Metoprolol', 'Lopressor', '50mg', 'TABLET', 'Beta blocker', 'Novartis'),
('Lisinopril', 'Zestril', '10mg', 'TABLET', 'ACE inhibitor', 'AstraZeneca'),
('Salbutamol', 'Ventolin', '100mcg', 'INHALER', 'Bronchodilator', 'GSK');

-- ============================================================
-- PATIENT MEDICATIONS (Active)
-- ============================================================
INSERT INTO `patient_medications` (`patient_id`, `medication_id`, `dosage`, `frequency`, `route`, `started_at`, `status`) VALUES
(1, 1, '500mg', 'Twice daily (Morning & Evening with food)', 'ORAL', '2025-03-01', 'ACTIVE'),
(3, 2, '5mg', 'Once daily', 'ORAL', '2024-06-15', 'ACTIVE'),
(3, 3, '20mg', 'Once at night', 'ORAL', '2024-06-15', 'ACTIVE');

-- ============================================================
-- LAB REPORTS
-- ============================================================
INSERT INTO `lab_reports` (`patient_id`, `report_name`, `test_date`, `laboratory_name`, `result_summary`, `verified`) VALUES
(1, 'Complete Blood Count (CBC)', '2026-08-10', 'Popular Diagnostic Centre', 'WBC: 6.8 K/uL, RBC: 4.9 M/uL, Hgb: 13.8 g/dL, Plt: 245 K/uL - Within normal range', 1),
(1, 'Fasting Blood Glucose (FBS)', '2026-08-10', 'Popular Diagnostic Centre', 'FBS: 118 mg/dL (Ref: 70-100) - Slightly elevated, follow-up recommended', 1),
(1, 'Lipid Profile', '2026-07-15', 'Ibn Sina Diagnostic', 'Total Cholesterol: 198, LDL: 125, HDL: 45, TG: 140 - borderline', 1),
(2, 'Thyroid Function Test', '2026-09-01', 'Labaid Diagnostics', 'TSH: 2.8 mIU/L - Within normal range', 1),
(3, 'ECG', '2026-08-20', 'ABC Hospital Cardiology Dept', 'Sinus rhythm, HR: 72 bpm, No significant ST-T changes', 1);

-- ============================================================
-- CONSULTATIONS
-- ============================================================
INSERT INTO `consultations` (`consultation_uid`, `patient_id`, `doctor_id`, `hospital_id`, `department_id`, `consultation_type`, `chief_complaint`, `symptoms`, `clinical_notes`, `diagnosis_summary`, `advice`, `follow_up_date`, `consultation_date`) VALUES
('CONS-20260820-0001', 1, 1, 1, 1, 'IN_PERSON', 'Fever and body ache for 3 days', 'High fever (103°F), generalized body ache, headache, mild sore throat', 'Patient appears febrile. Temp 38.9°C. Throat mildly erythematous. No lymphadenopathy. Lungs clear.', 'Acute viral fever', 'Rest, adequate hydration, antipyretics. Avoid NSAIDs given no known renal issues.', '2026-09-03', '2026-08-20 10:30:00'),
('CONS-20260916-0001', 1, 1, 1, 1, 'IN_PERSON', 'Follow-up for fever + routine check', 'Fever resolved. Now feeling better. Fatigue persists.', 'Afebrile. BP 118/76. HR 80. Patient recovered from viral fever. FBS slightly elevated on last test.', 'Resolved viral fever; Pre-diabetic monitoring required', 'Continue Metformin. Lifestyle changes. Low sugar diet. Regular exercise.', '2026-12-16', '2026-09-16 11:00:00'),
('CONS-20260820-0002', 1, 3, 1, 4, 'IN_PERSON', 'Stomach pain and acidity', 'Burning sensation in stomach, acid reflux, nausea after meals', 'Epigastric tenderness. No guarding. H. pylori test ordered.', 'Gastritis with GERD', 'Avoid spicy food. Small frequent meals. Elevate head of bed.', '2026-09-20', '2026-08-20 14:00:00');

-- ============================================================
-- DIAGNOSES
-- ============================================================
INSERT INTO `diagnoses` (`consultation_id`, `diagnosis_name`, `diagnosis_code`, `diagnosis_type`) VALUES
(1, 'Acute Viral Fever', 'A09', 'PRIMARY'),
(2, 'Resolved Viral Fever', 'Z87.89', 'PRIMARY'),
(2, 'Pre-diabetes (Monitoring)', 'R73.09', 'SECONDARY'),
(3, 'Acute Gastritis', 'K29.0', 'PRIMARY'),
(3, 'Gastroesophageal Reflux Disease', 'K21.0', 'SECONDARY');

-- ============================================================
-- PRESCRIPTIONS
-- ============================================================
INSERT INTO `prescriptions` (`prescription_uid`, `consultation_id`, `patient_id`, `doctor_id`, `hospital_id`, `diagnosis`, `advice`, `follow_up_date`, `status`) VALUES
('RX-20260820-0001', 1, 1, 1, 1, 'Acute Viral Fever', 'Rest, hydration, avoid heavy activity. Return if temperature persists beyond 5 days.', '2026-09-03', 'COMPLETED'),
('RX-20260820-0002', 3, 1, 3, 1, 'Gastritis with GERD', 'Avoid spicy, oily food. Eat small frequent meals. Sleep with head elevated. No NSAIDs.', '2026-09-20', 'ACTIVE'),
('RX-20260916-0001', 2, 1, 1, 1, 'Resolved Fever + Pre-diabetic Monitoring', 'Low glycemic diet. 30 min daily walk. Recheck FBS in 3 months.', '2026-12-16', 'ACTIVE');

-- ============================================================
-- PRESCRIPTION MEDICINES
-- ============================================================
INSERT INTO `prescription_medicines` (`prescription_id`, `medication_id`, `medicine_name`, `strength`, `dosage`, `frequency`, `duration`, `route`, `instructions`) VALUES
-- RX-20260820-0001 (Fever)
(1, 6, 'Paracetamol', '500mg', '500mg', '3 times daily', '5 days', 'ORAL', 'Take after meals. For fever only.'),
(1, 7, 'Cetirizine', '10mg', '10mg', 'Once at night', '3 days', 'ORAL', 'For allergic symptoms if present'),
-- RX-20260820-0002 (Gastritis)
(2, 4, 'Omeprazole', '20mg', '20mg', 'Twice daily (before breakfast & dinner)', '4 weeks', 'ORAL', 'Take 30 minutes before meals'),
(2, 8, 'Pantoprazole', '40mg', '40mg', 'Once at night', '2 weeks', 'ORAL', 'For GERD control'),
-- RX-20260916-0001 (Follow-up)
(3, 1, 'Metformin', '500mg', '500mg', 'Twice daily with meals', 'Ongoing', 'ORAL', 'Continue existing dose. Monitor for GI side effects.');

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
INSERT INTO `notifications` (`user_id`, `type`, `title`, `message`, `reference_type`, `is_read`) VALUES
(8, 'PRESCRIPTION', 'New Prescription Issued', 'Dr. Farhana Rahman has issued a new prescription (RX-20260916-0001) for your recent consultation.', 'prescription', 0),
(8, 'ACCESS_REQUEST', 'Medical Record Access Request', 'Dr. Farhana Rahman at ABC General Hospital is requesting access to your medical records. Please review the request in your Consent Requests section.', 'access_request', 0),
(5, 'SYSTEM', 'Affiliation Approved', 'Your affiliation with ABC General Hospital has been approved. You can now login through ABC General Hospital.', 'affiliation', 1);

-- ============================================================
-- AUDIT LOGS (Sample)
-- ============================================================
INSERT INTO `audit_logs` (`user_id`, `action`, `target_type`, `patient_id`, `doctor_id`, `hospital_id`, `ip_address`, `severity`, `metadata`) VALUES
(5, 'LOGIN', 'user', NULL, 1, 1, '192.168.1.100', 'INFO', '{"hospital": "ABC General Hospital", "method": "OTP"}'),
(5, 'PATIENT_SEARCH', 'patient', 1, 1, 1, '192.168.1.100', 'INFO', '{"search_by": "patient_uid", "query": "PT-000001"}'),
(5, 'ACCESS_REQUEST', 'access_request', 1, 1, 1, '192.168.1.100', 'INFO', '{"requested_data": ["MEDICAL_HISTORY","PRESCRIPTIONS","MEDICATIONS","ALLERGIES"]}'),
(5, 'CREATE_PRESCRIPTION', 'prescription', 1, 1, 1, '192.168.1.100', 'INFO', '{"prescription_uid": "RX-20260916-0001", "medicines_count": 1}');
