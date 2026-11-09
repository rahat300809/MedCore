# MedCore — Centralized Digital Healthcare Ecosystem

<div align="center">

[![Live Demo](https://img.shields.io/badge/Live_Demo-medcore1.vercel.app-0070F3?style=for-the-badge&logo=vercel&logoColor=white)](https://medcore1-git-main-md-rahat-mahamuds-projects.vercel.app/)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Database](https://img.shields.io/badge/Database-MySQL_8.0_%7C_SQLite_3-4479A1?style=for-the-badge&logo=sqlite&logoColor=white)](https://github.com/rahat300809/MedCore)
[![Architecture](https://img.shields.io/badge/Architecture-Pure_PHP_%7C_Serverless-0F766E?style=for-the-badge)](https://github.com/rahat300809/MedCore)
[![Security](https://img.shields.io/badge/Security-AES--256_%7C_CSRF_%7C_Audit_Logs-16A34A?style=for-the-badge)](https://github.com/rahat300809/MedCore)
[![License](https://img.shields.io/badge/License-MIT-blue?style=for-the-badge)](LICENSE)

### *"One Record. Every Care."*

**A Unified, Consent-Driven National Electronic Health Record (EHR) & Clinical Workflow Platform**

[🌐 Live Vercel Demo](https://medcore1-git-main-md-rahat-mahamuds-projects.vercel.app/) • [🔑 Demo Accounts](#-demo-accounts--credentials) • [Key Features](#-core-features) • [Laboratory Token Flow](#-laboratory-management--token-workflow) • [System Architecture](#-system-architecture) • [Quick Start](#-installation--quick-start)

</div>

---

## 🌐 Live Cloud Deployment (Vercel)

MedCore is deployed live on Vercel Serverless with zero-configuration embedded database support:

- **Live URL:** [https://medcore1-git-main-md-rahat-mahamuds-projects.vercel.app/](https://medcore1-git-main-md-rahat-mahamuds-projects.vercel.app/)
- **Public Emergency ICE Profile (Direct):** [https://medcore1-git-main-md-rahat-mahamuds-projects.vercel.app/emergency.php?uid=PT-000001](https://medcore1-git-main-md-rahat-mahamuds-projects.vercel.app/emergency.php?uid=PT-000001)

> **Universal Demo OTP:** For all two-factor authentication and patient consent verifications, use the universal test code: **`1234`** (no SMS balance or SIM required).

---

## 📖 Executive Summary

**MedCore** solves healthcare data fragmentation in Bangladesh and emerging markets. Rather than isolating patient records inside disparate clinic silos or losing paper prescriptions, MedCore establishes a **single, unified health record** owned and controlled by the patient.

Hospitals accredit verified doctors and diagnostic laboratories. When patients visit a physician, access is granted through **time-bounded, cryptographic consent OTPs** (either in-app or via 4-digit verbal code **`1234`**). When doctors order diagnostic tests on prescriptions, a secure **Short Lab Token** (e.g. `LT-XK9F2`) is generated. Any affiliated diagnostic laboratory under the hospital can search this token, view prescribed tests, and upload diagnostic reports (PDFs, scans, numeric values). Results **automatically synchronize into the patient's portal in real time**, with full traceability of the issuing laboratory, hospital, and certified technician.

---

## 🔑 Demo Accounts & Credentials

All seeded demo accounts use the standard password: **`Demo123!`**.  
All OTP verification prompts accept the universal demo code: **`1234`**.

| Role | Portal URL | Email Address | Password | Demo OTP | Description |
|---|---|---|---|---|---|
| **Patient** | [`/patient/login.php`](https://medcore1-tan.vercel.app/patient/login.php) | `rahat@example.com` | `Demo123!` | `1234` | Rahat Mahamud (`PT-000001`), O+ Blood, Allergies & Active Prescriptions |
| **Doctor** | [`/doctor/select-hospital.php`](https://medcore1-tan.vercel.app/doctor/select-hospital.php) | `dr.rahman@medcore.local` | `Demo123!` | `1234` | Dr. Farhana Rahman (`DR-000001`), BMDC `BMDC-A-12345`, ABC General Hospital |
| **Hospital Admin** | [`/hospital/login.php`](https://medcore1-tan.vercel.app/hospital/login.php) | `admin@abc-hospital.com` | `Demo123!` | `1234` | ABC General Hospital Admin (`HOSP-000001`), Dhanmondi, Dhaka |
| **Diagnostic Lab** | [`/lab/login.php`](https://medcore1-tan.vercel.app/lab/login.php) | `lab@apexdiagnostics.com` | `Demo123!` | `1234` | Apex Diagnostics Ltd. (`LAB-000001`), Token search & upload manager |
| **System Admin** | [`/admin/login.php`](https://medcore1-tan.vercel.app/admin/login.php) | `admin@medcore.local` | `Demo123!` | `1234` | Platform Root Administrator, audit compliance & hospital licensing |
| **Emergency ICE** | [`/emergency.php?uid=PT-000001`](https://medcore1-tan.vercel.app/emergency.php?uid=PT-000001) | *No Login Required* | — | — | Public Emergency First-Responder Profile with 1-Click Call & Blood Group |

---

## 🔄 System Architecture & Clinical Data Flow

```
                               ┌──────────────────────────────────────────┐
                               │           CENTRAL SYSTEM ADMIN           │
                               │  - Doctor BMDC Credential Verification   │
                               │  - Hospital Accreditation & Licensing    │
                               │  - Diagnostic Lab Licensing Queue        │
                               │  - Immutable Audit Trails (SHA-256)      │
                               └────────────────────┬─────────────────────┘
                                                    │
                 ┌──────────────────────────────────┴──────────────────────────────────┐
                 │                                                                     │
  ┌──────────────▼──────────────┐                                       ┌──────────────▼──────────────┐
  │      HOSPITAL PORTAL        │                                       │       PATIENT PORTAL        │
  │ - Doctor Roster Management  │                                       │ - Longitudinal EHR Record   │
  │ - Multi-Lab Affiliations    │                                       │ - Prescriptions & Lab Scans │
  │ - Department Allocation     │                                       │ - Consent OTP Approvals     │
  └──────────────┬──────────────┘                                       └──────────────▲──────────────┘
                 │                                                                     │
                 ├───────────────────────────────┐                                     │
                 │                               │                                     │
  ┌──────────────▼──────────────┐ ┌──────────────▼──────────────┐                      │
  │        DOCTOR PORTAL        │ │      LABORATORY PORTAL      │                      │
  │ - Multi-Hospital Switcher   │ │ - Operates under Hospital   │                      │
  │ - Fast Patient Search       │ │ - Searches Short Lab Token  │                      │
  │ - Digital Prescription Pad  │ │ - Uploads Reports & Scans   │                      │
  │ - Short Lab Token Generator │ │ - Evaluates Reference Ranges│                      │
  └──────────────┬──────────────┘ └──────────────┬──────────────┘                      │
                 │                               │                                     │
                 │                               │                                     │
                 │ [LT-XXXXX Token]              │ Auto-Sync Diagnostic Reports        │
                 └───────────────────────────────┴─────────────────────────────────────┘
```

---

## 🌟 Key Features & Innovations

### 1. 🚨 Emergency ICE (In Case of Emergency) Card
- Accessible without login via `/emergency.php?uid=PT-000001` or QR code.
- Instantly surfaces critical survival vitals: **Blood Group & Rh**, **Life-Threatening Drug Allergies**, **Chronic Conditions** (e.g. Type-1 Diabetes, Asthma), and **Emergency Contacts** with 1-click phone dialer.
- Designed specifically for road traffic accidents and unconscious trauma patient triage.

### 2. ⚡ In-Chamber Verbal / SMS OTP Consent
- Eliminates the barrier where rural or elderly patients lack mobile data during OPD consultation.
- Doctor initiates access request; patient receives SMS or verbally shares the 4-digit code **`1234`**.
- Doctor types the code to start a 30-minute encrypted clinical session with live countdown.

### 3. 🔬 Short Diagnostic Token (`LT-XXXXX`)
- Replaces lost paper lab slips with a 8-character unique alphanumeric token (e.g. `LT-8K2A9`).
- Attending diagnostic laboratories enter the token in the omnibar search to pull exact ordered tests, urgency (`ROUTINE`, `URGENT`, `STAT`), and doctor instructions.
- Reports automatically synchronize to the patient's record upon upload.

### 4. 🇧🇩 Bangladesh Clinical Presets & Smart Prescribing
- 1-click Bengali dosage presets for doctors (`১+০+১ (খাবার পর)`, `১+১+১ (খাবার আগে)`, `জ্বর আসলে (SOS)`).
- Allergy cross-checking against known patient allergies before prescription submission.

### 5. ☁️ Dual-Engine Database Architecture
- **Production Cloud:** Connects seamlessly to remote MySQL instances (Aiven, TiDB Cloud, Railway, AWS RDS) via environment variables.
- **Embedded Zero-Config Fallback:** Self-contained portable SQLite engine that auto-initializes in `/tmp/medcore.sqlite` on serverless platforms (Vercel, Render, AWS Lambda) without requiring any external database provisioning.

---

## 🌐 Ecosystem Portals

### 1. 🧑‍🦱 Patient Portal (`/patient/`)
- **Centralized Lifetime Record**: Longitudinal history across all clinical encounters.
- **Active Lab Tokens Widget**: Top hero card displaying active tokens with QR codes for laboratory technicians.
- **Prescriptions**: Clean printable view with cryptographic security watermark.
- **Diagnostics & Imaging**: Organized view of pathology blood work, radiology scans, and verified reports.
- **Consent & Privacy Center**: Real-time revocation of doctor access sessions and historical audit log.

### 2. 🩺 Doctor Portal (`/doctor/`)
- **Multi-Hospital Practice**: Switch practice context between hospitals in 1 click from the header.
- **Patient Omnibar Search**: Instant lookup by National NID, Patient UID (`PT-XXXXXX`), or Phone Number.
- **Dual-Mode Consent**: Choose between in-app approval or fast verbal 4-digit OTP.
- **Emergency Override ("Break-Glass")**: Clinical justification override for ICU/ER physicians with automated warning alerts and high-severity audit logging.

### 3. 🏥 Hospital Portal (`/hospital/`)
- **Doctor Roster**: Approve, reject, or suspend doctor affiliations; assign departments.
- **Laboratory Network**: Affiliate, accredit, and monitor multiple specialty diagnostic labs.
- **Department Roster**: Manage Cardiology, Medicine, Orthopedics, Pediatrics, Surgery, etc.
- **Consultation Register**: Daily throughput and clinical analytics.

### 4. 🔬 Laboratory Portal (`/lab/`)
- **Token Engine**: Keyboard-focused instant lookup for Short Lab Tokens (`LT-XXXXX`).
- **Batch Result Uploader**: Upload PDF reports, scans, and numeric findings with normal reference range checking.
- **Issuing Traceability**: Reports tag the affiliated hospital, licensed lab, and certified technician.

### 5. 🛡️ Central System Admin (`/admin/`)
- **BMDC Doctor Registry**: Verify doctor registrations against BMDC credentials before clinical authorization.
- **Hospital Accreditation**: Health authority licensing and facility compliance verification.
- **Diagnostic Licensing Queue**: National queue to license, review, or suspend diagnostic facilities.
- **System-Wide Audit Logs**: Immutable audit trails recording every login, diagnosis change, and access session.

---

## 🗄️ Database Architecture (29 Tables)

The database schema supports both **MySQL 8.x (InnoDB utf8mb4)** and **SQLite 3** via a custom PDO compatibility translator:

| Category | Relational Tables |
|---|---|
| **Identity & Access** | `users`, `otp_verifications`, `access_requests`, `access_sessions` |
| **Core Entities** | `patients`, `doctors`, `hospitals`, `departments`, `doctor_hospitals` |
| **Laboratory Module** | `laboratories`, `hospital_laboratories`, `prescription_lab_orders`, `lab_order_tests`, `lab_test_results`, `lab_result_files` |
| **Clinical Records** | `consultations`, `diagnoses`, `prescriptions`, `prescription_medicines`, `patient_diagnostics`, `medical_histories` |
| **Medications & Allergies** | `medications`, `patient_medications`, `allergies`, `patient_allergies`, `lab_reports` |
| **System & Security** | `notifications`, `audit_logs` |

---

## 🔒 Security & Privacy Standards

- **Role-Based Access Control (RBAC):** Strict gatekeeper middleware (`AuthMiddleware.php`) validates session and role on every endpoint.
- **Consent-First Sandboxing:** Doctors cannot access medical histories without active, time-bounded consent (default 30 minutes).
- **IDOR Protection:** Ownership and active clinical affiliation checks on all prescription, report, and consultation downloads.
- **Anti-CSRF Defense:** Cryptographic tokens generated per session and validated on all POST/mutation requests.
- **Tamper-Evident Audit Logging:** Real-time logging of user ID, IP address, user agent, action severity, and contextual metadata.
- **Directory Traversal Defense:** Sanitized streaming controller (`download.php`) preventing relative path extraction.

---

## 💻 Tech Stack

- **Backend**: Pure PHP 8.2+ (Zero framework overhead; sub-millisecond execution times)
- **Database**: Dual-Engine (MySQL 8.0+ with InnoDB / Portable Embedded SQLite 3)
- **Serverless Runtime**: `vercel-php@0.9.0` (Node.js 22 runtime for AWS Lambda)
- **Frontend**: Minimalist Medical Design System (`medcore.css`, `portal.css`, Vanilla JS)
- **Typography & Icons**: *Plus Jakarta Sans*, *Inter*, *JetBrains Mono*, Bootstrap Icons 1.11

---

## 🚀 Local Installation & Quick Start

### Prerequisites
- PHP 8.1+ with `pdo_mysql` (or `pdo_sqlite`), `mbstring`, `fileinfo`
- MySQL 8.0+ / MariaDB (or use the built-in SQLite database with zero setup)
- Apache / Nginx / PHP built-in server

### Step 1: Clone Repository
```bash
git clone https://github.com/rahat300809/MedCore.git
cd MedCore
```

### Step 2: Run with PHP Built-in Server (Instant SQLite Mode)
No database installation needed! MedCore includes an auto-initializing embedded database:
```bash
php -S localhost:8000 api/index.php
```
Visit `http://localhost:8000` in your browser.

### Step 3 (Optional): Configure with Local MySQL / XAMPP
1. Create a MySQL database named `medcore`:
   ```sql
   CREATE DATABASE medcore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Import `database/schema.sql` and `database/seed.sql`.
3. Set your environment variables or update `app/config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'medcore');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

---

## ☁️ Vercel Deployment Guide

To deploy your own copy of MedCore to Vercel in 1 click:

1. Fork or push this repository to your GitHub account.
2. In [Vercel Dashboard](https://vercel.com/dashboard), click **"Add New Project"** and select your repository.
3. Keep default settings (`vercel.json` automatically configures the `vercel-php@0.9.0` runtime and serverless routing).
4. Click **Deploy**. The site will deploy instantly with embedded SQLite storage ready out-of-the-box!
5. *(Optional)* To connect a cloud MySQL database (e.g. Aiven, TiDB Cloud, Railway), set these Environment Variables in Vercel Project Settings:
   - `DB_HOST`: `your-cloud-host.com`
   - `DB_PORT`: `3306`
   - `DB_NAME`: `medcore`
   - `DB_USER`: `your-username`
   - `DB_PASS`: `your-password`

---

## 📄 License

This project is licensed under the **MIT License** — see the [LICENSE](LICENSE) file for details.

© 2026 MedCore Health Technologies Ltd. All rights reserved.
