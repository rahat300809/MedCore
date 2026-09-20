# MedCore — Centralized Digital Healthcare Ecosystem

<div align="center">

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![Architecture](https://img.shields.io/badge/Architecture-Pure_PHP_%7C_PDO-0F766E?style=for-the-badge)](https://github.com/rahat300809/MedCore)
[![Security](https://img.shields.io/badge/Security-AES--256_%7C_CSRF_%7C_Audit_Logs-16A34A?style=for-the-badge)](https://github.com/rahat300809/MedCore)
[![License](https://img.shields.io/badge/License-MIT-blue?style=for-the-badge)](LICENSE)

### *"One Record. Every Care."*

**A Unified, Consent-Driven National Electronic Health Record (EHR) & Clinical Workflow Platform**

[Key Features](#-core-features) • [System Architecture](#-system-architecture) • [Laboratory Token Flow](#-laboratory-management--token-workflow) • [Portals](#-ecosystem-portals) • [Quick Start](#-installation--quick-start) • [Demo Accounts](#-demo-accounts)

</div>

---

## 📖 Executive Summary

**MedCore** solves healthcare data fragmentation in Bangladesh and emerging markets. Rather than isolating patient records inside disparate clinic silos or losing paper prescriptions, MedCore establishes a **single, unified health record** owned and controlled by the patient.

Hospitals accredit verified doctors and diagnostic laboratories. When patients visit a physician, access is granted through **time-bounded, cryptographic consent OTPs**. When doctors order diagnostic tests on prescriptions, a secure **Short Lab Token** (e.g. `LT-XK9F2`) is generated. Any affiliated diagnostic laboratory under the hospital can search this token, view prescribed tests, and upload diagnostic reports (PDFs, scans, numeric values). Results **automatically synchronize into the patient's portal in real time**, with full traceability of the issuing laboratory, hospital, and certified technician.

---

## 🔄 System Architecture & Clinical Data Flow

```
                               ┌──────────────────────────────────────────┐
                               │           CENTRAL SYSTEM ADMIN           │
                               │  - Doctor BMDC Credential Verification   │
                               │  - Hospital Accreditation & Licensing    │
                               │  - Laboratory License Verification       │
                               │  - Tamper-Evident Audit Logs             │
                               └────────────────────┬─────────────────────┘
                                                    │
                 ┌──────────────────────────────────┴──────────────────────────────────┐
                 │                                                                     │
  ┌──────────────▼──────────────┐                                       ┌──────────────▼──────────────┐
  │      HOSPITAL PORTAL        │                                       │       PATIENT PORTAL        │
  │ - Doctor Roster Management  │                                       │ - Lifetime Health Records   │
  │ - Multi-Lab Affiliations    │                                       │ - Prescriptions & Scans     │
  │ - Clinical Consultations    │                                       │ - Consent OTP Authorization │
  └──────────────┬──────────────┘                                       └──────────────▲──────────────┘
                 │                                                                     │
                 ├───────────────────────────────┐                                     │
                 │                               │                                     │
  ┌──────────────▼──────────────┐ ┌──────────────▼──────────────┐                      │
  │        DOCTOR PORTAL        │ │      LABORATORY PORTAL      │                      │
  │ - Multi-Hospital Practice   │ │ - Operates under Hospital   │                      │
  │ - Patient Search (with OTP) │ │ - Searches Lab Token        │                      │
  │ - Issue Digital Prescription│ │ - Uploads Scans / PDFs      │                      │
  │ - Generates Short Lab Token │ │ - Inputs Numeric Findings   │                      │
  └──────────────┬──────────────┘ └──────────────┬──────────────┘                      │
                 │                               │                                     │
                 │                               │                                     │
                 │ [LT-XK9F2 Token]              │ Auto-Sync Reports                   │
                 └───────────────────────────────┴─────────────────────────────────────┘
```

---

## 🔬 Laboratory Management & Token Workflow

The Laboratory module provides an end-to-end diagnostic workflow connecting doctors, diagnostic facilities, and patients.

### Complete Step-by-Step Flow:
1. **Hospital Affiliation**: Hospitals can affiliate multiple independent diagnostic laboratories (`hospital_laboratories`). One hospital can have multiple specialty labs (Pathology, Radiology, Molecular Biology, Biochemistry).
2. **Doctor Prescribes Lab Tests**: During patient consultation, the doctor prescribes required tests (e.g., *Complete Blood Count (CBC)*, *Serum Creatinine*, *Chest X-Ray*) with clinical notes and urgency indicators (`ROUTINE`, `URGENT`, `STAT`).
3. **Short Lab Token Generated**: Upon saving the prescription:
   - A unique 8-character token (format: **`LT-XXXXX`**, e.g., `LT-8K2A9`) is generated.
   - The token is prominently displayed on the patient's printable official E-Prescription (`/patient/prescription-report.php`).
   - The patient receives an instant in-app notification with their token.
4. **Patient Presents Token at Lab**: The patient visits any affiliated diagnostic facility under the hospital and presents their token.
5. **Lab Staff Looks Up Token**: In `/lab/search-token.php`, the lab technician types the token to retrieve:
   - Patient name and UID (masked for privacy)
   - Prescribing doctor and hospital origin
   - Complete list of ordered tests with instructions and urgency
6. **Result & File Upload**: In `/lab/upload-results.php`, lab staff can:
   - Upload multiple files (PDF reports, high-resolution scan images, JPG/PNG/WEBP).
   - Enter numeric findings, reference ranges (e.g. `14.5 g/dL`, Ref: `13.0 - 17.0`), and diagnostic summaries.
   - Tag clinical status (`NORMAL`, `ABNORMAL`, `CRITICAL`, `PENDING_REVIEW`).
   - Digitally sign with the certified technician's name.
7. **Auto-Addition to Patient Record**:
   - The test result automatically reflects in the patient's portal (`/patient/lab-reports.php`).
   - Once all tests in an order are completed, the order status flips to `COMPLETED`.
   - The patient is notified that their diagnostic report is ready.
8. **Clinical Issuance Tracking**: The patient's portal explicitly displays:
   - **Issuing Laboratory**: Laboratory Name & Lab UID (`LAB-000001`).
   - **Parent Hospital**: The hospital under which the test was authorized.
   - **Certified Technician**: Name and timestamp of upload.
   - Direct download and preview buttons for all attached documents.

---

## 🌐 Ecosystem Portals

### 1. 🧑‍🦱 Patient Portal (`/patient/`)
- **Centralized Health Record**: Complete longitudinal history across all hospital visits.
- **Prescriptions**: Interactive and printable prescriptions with cryptographic hash verification.
- **Diagnostic Imaging & Labs**: Tabbed access to Radiology (X-Ray, MRI, CT, Ultrasound) and Token-tracked Pathology results.
- **Consent & Privacy Center**: Real-time management of doctor access requests, active sessions, and historical access audits.
- **Allergies & Chronic Conditions**: Critical clinical alerts flagged to attending physicians.

### 2. 🩺 Doctor Portal (`/doctor/`)
- **Multi-Hospital Practice**: Doctors can practice across multiple hospitals with single-credential login.
- **Patient Lookup & Consent**: Search patients by national UID or phone; access medical history strictly upon OTP approval.
- **Time-Bounded Clinical Sessions**: Consultations run in secure 30-minute encrypted sessions.
- **Smart Prescription Builder**: Medication search, dosage/frequency/route builders, allergy contradiction warnings, clinical diagnosis, and **Laboratory Test Token generation**.

### 3. 🏥 Hospital Portal (`/hospital/`)
- **Doctor Roster**: Approve, suspend, or reject doctor affiliation applications; assign department designations.
- **Laboratory Roster**: Affiliate, monitor, and manage multiple diagnostic laboratories under the hospital umbrella.
- **Departmental Management**: Organize specialty departments (Cardiology, Orthopedics, Oncology, etc.).
- **Consultation & Diagnostics Register**: Full oversight over daily outpatient and inpatient clinical records.

### 4. 🔬 Laboratory Portal (`/lab/`)
- **Hospital Context Selector**: Switch between affiliated hospitals if the lab serves multiple hospital networks.
- **Token Lookup Engine**: Fast alphanumeric token search (`LT-XXXXX`).
- **Batch Result Uploader**: Drag-and-drop multi-file upload (PDF, JPG, PNG) with reference range evaluation.
- **Archive & Audit**: History of all processed orders, completed reports, and technician actions.

### 5. 🛡️ Central System Admin (`/admin/`)
- **BMDC Doctor Verification**: Review doctor registrations against BMDC registry numbers before network activation.
- **Hospital Accreditation**: Verify health authority licenses and manage facility credentials.
- **Diagnostic Lab Licensing**: Central authority queue to approve, reject with reason, suspend, or reactivate diagnostic laboratories.
- **System-Wide Audit Logs**: Immutable audit trails logging every login, consent grant, diagnosis change, and report upload.

---

## 🗄️ Database Architecture (29 Tables)

The database runs on MySQL 8.x with InnoDB engines, foreign key constraints, and utf8mb4 encoding:

| Category | Tables |
|---|---|
| **Identity & Access** | `users`, `otp_verifications`, `access_requests`, `access_sessions` |
| **Core Entities** | `patients`, `doctors`, `hospitals`, `departments`, `doctor_hospitals` |
| **Laboratory Module** | `laboratories`, `hospital_laboratories`, `prescription_lab_orders`, `lab_order_tests`, `lab_test_results`, `lab_result_files` |
| **Clinical Records** | `consultations`, `diagnoses`, `prescriptions`, `prescription_medicines`, `patient_diagnostics`, `medical_histories` |
| **Medications & Allergies** | `medications`, `patient_medications`, `allergies`, `patient_allergies`, `lab_reports` |
| **System & Security** | `notifications`, `audit_logs` |

---

## 🔒 Security & Privacy Standards

- **Zero Unauthenticated Access**: Role-based access control (`AuthMiddleware.php`) enforces permissions on every single route.
- **Consent-First Architecture**: Doctors cannot view any patient data without active OTP confirmation from the patient.
- **Session Sandboxing**: Doctor consultations expire after 30 minutes; laboratory staff can only process tokens issued under their affiliated hospital.
- **Tamper-Evident Audit Logging**: Every view, print, edit, token lookup, and file download is permanently recorded in `audit_logs`.
- **Form Security**: Anti-CSRF token verification on all POST submissions.
- **Data Protection**: Password hashing via Bcrypt; file upload validation enforcing strict extension and MIME-type restrictions.

---

## 💻 Tech Stack

- **Backend**: Native PHP 8.1+ (No heavy frameworks; lightning-fast execution and zero dependency bloat)
- **Database**: MySQL 8.0+ / MariaDB with PDO prepared statements
- **Frontend**: Semantic HTML5, Vanilla JavaScript, Custom CSS Design System (`medcore.css`, `portal.css`)
- **Typography & Icons**: Google Fonts (*Plus Jakarta Sans*, *Inter*, *JetBrains Mono*), Bootstrap Icons 1.11
- **Web Server**: Apache 2.4+ (XAMPP / Laragon / Nginx via PHP-FPM)

---

## 🚀 Installation & Quick Start

### Prerequisites
- PHP 8.1 or higher (with `pdo_mysql`, `mbstring`, `fileinfo` extensions enabled)
- MySQL 8.0 or MariaDB 10.4+
- Apache web server (e.g. via XAMPP)

### Step 1: Clone Repository
```bash
git clone https://github.com/rahat300809/MedCore.git C:/xampp/htdocs/medcore
```

### Step 2: Configure Database
1. Open MySQL / phpMyAdmin.
2. Create database `medcore`:
   ```sql
   CREATE DATABASE medcore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import the database schemas in order:
   - `database/schema.sql` (Base database)
   - `database/migrate_lab_module.sql` (Laboratory module tables & ENUM extensions)
   - `database/seed.sql` (Demo data)

4. Configure database credentials in [`app/config/database.php`](file:///f:/medcore/app/config/database.php):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'medcore');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

### Step 3: Launch
Open your browser and navigate to:
- **Landing Page**: `http://localhost/medcore/` or `http://localhost/medcore/public/`
- **Role Selection Portal**: `http://localhost/medcore/role-selection.php`
- **Patient Portal**: `http://localhost/medcore/patient/login.php`
- **Doctor Portal**: `http://localhost/medcore/doctor/select-hospital.php`
- **Hospital Portal**: `http://localhost/medcore/hospital/login.php`
- **Laboratory Portal**: `http://localhost/medcore/lab/login.php`
- **Central Admin**: `http://localhost/medcore/admin/login.php`

---

## 🔑 Demo Accounts

All demo accounts use password: `DemoPassword123!` *(or `Demo123!` based on your seeded bcrypt hash)*. In development mode (`OTP_DEV_MODE = true`), OTP codes are displayed directly on screen for testing ease.

| Role | Email | Identifier / UID | Purpose |
|---|---|---|---|
| **System Admin** | `admin@medcore.local` | `ADMIN` | Central board verification & oversight |
| **Hospital Admin** | `admin@abc-hospital.com` | `HOSP-000001` | Hospital doctor & laboratory roster |
| **Doctor** | `dr.rahman@medcore.local` | Reg: `BMDC-A-12345` | Prescriptions & lab test ordering |
| **Diagnostic Lab** | `lab@apexdiagnostics.com` | `LAB-000001` | Token search & report file upload |
| **Patient** | `rahat@example.com` | `PAT-000001` | Medical records & lab results view |

---

## 🧪 Testing the Complete Lab Token Flow

1. **Doctor creates prescription**:
   - Log in as Doctor (`dr.rahman@medcore.local`).
   - Create prescription for patient `PAT-000001`.
   - Add Lab Tests (e.g. *Complete Blood Count* & *Lipid Profile*).
   - Save prescription → Take note of generated token (e.g., `LT-8K2A9`).
2. **Lab fulfills tests**:
   - Log in to Lab Portal at `/lab/login.php`.
   - Go to **Search Token** → Enter `LT-8K2A9`.
   - Click **Upload Results** → Upload PDF/image report, enter findings, and save.
3. **Patient verifies result**:
   - Log in as Patient at `/patient/login.php`.
   - Open **Diagnostics & Lab Reports** (`/patient/lab-reports.php`).
   - View completed report showing the issuing laboratory, parent hospital, and download attachment.

---

## 📄 License

This project is licensed under the **MIT License** — see the [LICENSE](LICENSE) file for details.

© 2026 MedCore Health Technologies Ltd. All rights reserved.
