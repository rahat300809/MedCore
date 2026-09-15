# MedCore — Secure Digital Healthcare Ecosystem

> **"One Record. Every Care."**

MedCore is a consent-driven, secure digital medical record platform connecting **Patients**, **Doctors**, and **Hospitals** in Bangladesh.

---

## 🚀 Quick Start (XAMPP)

### 1. Clone & Setup
```bash
git clone https://github.com/rahat300809/MedCore.git C:/xampp/htdocs/medcore
```

### 2. Database Setup
1. Start XAMPP → Start **Apache** and **MySQL**
2. Open phpMyAdmin → http://localhost/phpmyadmin
3. Import: `database/schema.sql`
4. Import: `database/seed.sql`

### 3. Update Password Hashes (Important!)
The seed.sql uses a placeholder hash. Update with real bcrypt hash:
```bash
php -r "echo password_hash('Demo123!', PASSWORD_BCRYPT);"
```
Then run this in phpMyAdmin:
```sql
UPDATE users SET password_hash = 'YOUR_HASH_HERE';
```

### 4. Access
- Landing Page: http://localhost/medcore/public/
- Patient Portal: http://localhost/medcore/patient/
- Doctor Portal: http://localhost/medcore/doctor/
- Hospital Portal: http://localhost/medcore/hospital/
- Admin Portal: http://localhost/medcore/admin/

---

## 🔑 Demo Accounts

| Role | Email | Password | Notes |
|------|-------|----------|-------|
| Patient | rahat@example.com | Demo123! | Full medical history |
| Doctor | dr.rahman@medcore.local | Demo123! | Affiliated with ABC Hospital |
| Doctor | dr.karim@medcore.local | Demo123! | Affiliated with ABC + XYZ |
| Hospital | admin@abc-hospital.com | Demo123! | Verified hospital |
| Admin | admin@medcore.local | Demo123! | System admin |

### Doctor Login:
1. Go to `/doctor/select-hospital.php`
2. Select **ABC General Hospital**
3. Enter Reg ID: `BMDC-A-12345`, Email: `dr.rahman@medcore.local`
4. Enter OTP (shown on screen in dev mode)

---

## 🏗️ Architecture

```
PHP 8.x + MySQL 8.x + Apache (XAMPP)
No Laravel | No Node.js | No MongoDB
Pure PHP with PDO prepared statements
```

### Security
- **CSRF** protection on every form
- **Bcrypt** password hashing
- **OTP** with bcrypt hash + 5-minute expiry
- **Doctor-Hospital affiliation** verified on every request
- **Patient consent** required for every medical record access
- **Access sessions** expire after 30 minutes
- **Audit logs** for every sensitive operation

### Database: 23 Tables
- `users`, `patients`, `doctors`, `hospitals`, `departments`
- `doctor_hospitals` — The affiliation backbone
- `medical_histories`, `allergies`, `patient_allergies`
- `medications`, `patient_medications`, `lab_reports`
- `consultations`, `diagnoses`, `prescriptions`, `prescription_medicines`
- `otp_verifications`, `access_requests`, `access_sessions`
- `notifications`, `audit_logs`

---

## 📁 Structure
```
medcore/
├── app/          # Controllers, Models, Services, Middleware
├── public/       # Landing page + assets
├── patient/      # Patient portal pages
├── doctor/       # Doctor portal pages
├── hospital/     # Hospital portal pages
├── admin/        # Admin portal pages
├── database/     # schema.sql + seed.sql
└── storage/      # Uploads + logs
```

---

## 🛠️ Technology Stack
- **Backend**: PHP 8.x (pure, no framework)
- **Database**: MySQL 8.x with InnoDB + utf8mb4
- **Frontend**: HTML5 + CSS3 (custom design system) + Vanilla JS
- **Icons**: Bootstrap Icons 1.11
- **Fonts**: Inter (Google Fonts)
- **PDF**: mPDF (for digital prescriptions)
- **Server**: Apache via XAMPP

---

## 📋 Development Phases

| Phase | Status | Description |
|-------|--------|-------------|
| 1 | ✅ | Database Schema (23 tables) + Seed Data |
| 2 | ✅ | Auth Middleware + CSRF + Sessions |
| 3 | 🔄 | Patient Portal (13 pages) |
| 4 | 📅 | Hospital Portal (14 pages) |
| 5 | 📅 | Doctor Auth + Portal (19 pages) |
| 6 | 📅 | Patient Consent Flow |
| 7 | 📅 | Prescription Ecosystem + PDF |
| 8 | 📅 | Admin Portal (12 pages) |
| 9 | 📅 | Security Audit |
| 10 | 📅 | E2E Integration Test |

---

## ⚙️ Configuration

Edit `app/config/database.php` for DB credentials.
Edit `app/config/app.php` for app settings (OTP mode, session lifetime, etc.).

**Default dev settings:**
- `OTP_DEV_MODE = true` (OTP shown on screen — change to false in production!)
- `APP_ENV = 'development'`

---

## License
MIT License — © 2026 MedCore Health Technologies Ltd.
