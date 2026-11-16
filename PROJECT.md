# MedCore — Project Idea, Motivation & Full Work

**Competition title:** MedCore: One Record. Every Care. For Bangladesh.  
**One-line pitch:** MedCore gives Bangladesh one consent-protected health record — so hospitals can safely track emergency care, and patients and doctors never lose the history behind every treatment.  
**Live demo:** https://medcore-main-2026.vercel.app  
**Repository:** https://github.com/rahat300809/MedCore

---

## 1. Motivation — Why this project exists

In Bangladesh, most patients still do not have a **single, complete digital health record**.

Their history is scattered across:

- different hospitals  
- private chambers  
- diagnostic labs  
- paper prescriptions  

Because of this:

- Doctors cannot always see **previous illnesses** before treating.  
- New medicines may clash with old conditions → **side effects and unsafe care**.  
- In hospital **cardiac arrest (Code Blue)**, an **8–15 person emergency team** struggles to record who gave which medicine, when CPR happened, and when a shock was given.  
- In normal consultations, patients often **forget** what the doctor said and **why** each medicine must be taken.

**MedCore was built to fix this trust and memory gap in care.**

---

## 2. Core idea

**MedCore** is a **consent-first national digital health platform** for Bangladesh.

It connects:

| Portal | Who uses it | Purpose |
|--------|-------------|---------|
| Patient | Citizens / patients | Own record, medicines, consent, Sidekick, ICE |
| Doctor | Registered / BMDC-linked doctors | OTP unlock, queue, Rx, AI brief, Code Blue |
| Hospital | Registered hospitals | Reception, roster, ER Code Blue |
| Laboratory | Affiliated labs | Lab token search & report sync |
| Admin | System governance | Verify doctors, hospitals, labs |

**Key principle:**  
A doctor or hospital can open a patient’s full record **only with the patient’s consent** (OTP, time-limited session).

---

## 3. Problem → Solution

### Problem A — No unified patient history
Doctors treat without full previous records → wrong or risky medicine choices.

**Solution:** One lifelong Patient UID (e.g. `PT-000001`) with longitudinal EHR across affiliated facilities.

### Problem B — Unsafe access to private data
Anyone should not freely open patient files.

**Solution:** Only **registered doctors** and **registered hospitals** join the grid. Access is **OTP consent + 30-minute session**, then auto-expire. Full audit trail.

### Problem C — Hospital Code Blue chaos
In cardiac arrest, the ER team cannot reliably note interventions in real time.

**Solution:** **Code Blue ACLS Co-Pilot**  
- Listens to the room (Audio → Text via Whisper / AudioToText pattern)  
- Groq AI cleans text and sorts interventions  
- Timers for CPR cycle / Epinephrine / pulse reminders  
- Generates AHA ACLS-style run sheet  

### Problem D — Patients forget consultation details
Patients leave the chamber confused about terms, questions, and medicines.

**Solution:** **Sidekick — Real-Time Patient Advocate**  
- Listens to doctor–patient conversation  
- Explains medical terms in simple Bangla + English  
- Suggests questions to ask the doctor  
- Saves appointment summary & next steps  
- Patient portal shows **which medicine is for what**  

### Problem E — Doctors need speed at bedside
Doctors need full history fast, not a 20-page scroll.

**Solution:** When a doctor opens a consented patient record, **AI makes a short bullet summary** of past illness, current medicines, and alerts.

---

## 4. What we built (full project work)

### 4.1 Platform foundations
- Multi-role portals (Patient, Doctor, Hospital, Lab, Admin)  
- SQLite/MySQL compatible backend (PHP)  
- Vercel serverless hosting (`vercel-php`)  
- CSRF protection, session auth, audit logging  
- Demo credentials for competition judges  

### 4.2 Consent & clinical workflow
- Patient search by UID / phone / NID  
- Access request → Patient OTP approve/deny  
- 30-minute encrypted clinical session  
- Digital prescription pad + lab tokens (`LT-XXXXX`)  
- Lab upload → auto sync to patient portal  
- OPD booking, reception desk, live doctor queue  
- Public **ICE** emergency card (blood group, allergy, emergency contact) without login  

### 4.3 AI features (competition differentiators)

**Code Blue (Hospital / Doctor ER perspective)**  
When a patient collapses into cardiac arrest, the hospital resuscitation team uses MedCore to:
1. Capture spoken updates from the team  
2. Convert audio → text  
3. Structure drugs, shocks, CPR, ROSC into a ledger  
4. Remind for pulse / epi intervals  
5. Export / generate ACLS report  

**Sidekick (Patient advocacy)**  
In the chamber:
1. Record / listen to consultation speech  
2. Explain jargon  
3. Suggest questions  
4. Generate summary  
5. Show medicine purpose on the patient dashboard  

**Doctor AI brief**  
On medical record open (with consent): short bullet list — past disease, current meds & purpose, allergies.

### 4.4 Design & UX
- International clinical UI (navy / teal, Manrope + Source Sans 3)  
- Full-bleed public landing with clear problem–solution sections  
- Portal shell with consistent navigation  
- Mobile-responsive public + portal layouts  

---

## 5. How it works (simple flow)

```
Patient registers → gets UID
Doctor/Hospital verified → joins MedCore
Patient books / visits chamber
Doctor requests access → Patient gives OTP
Doctor sees history + AI short summary
Doctor writes Rx / lab orders
Patient sees medicines + “why this medicine”
Lab uses LT-token → uploads report → patient sees result
Emergency: hospital opens Code Blue → AI logs resuscitation
Public emergency: ICE card via UID (no login)
```

---

## 6. Technology stack

| Layer | Choice |
|-------|--------|
| Backend | PHP 8+ |
| Database | MySQL / SQLite (Vercel-friendly) |
| Frontend | Semantic HTML + competition CSS design system |
| AI chat / clinical parse | Groq API (`GROQ_MODEL`) |
| Speech → text | Groq Whisper (`whisper-large-v3-turbo`), AudioToText-style pipeline |
| Hosting | Vercel Serverless |
| Security | Sessions, CSRF, role auth, audit logs, consent OTP |

---

## 7. Impact for Bangladesh

| Stakeholder | Benefit |
|-------------|---------|
| **Patients** | One record, medicine clarity, Sidekick guidance, ICE safety |
| **Doctors** | Safe history access, AI brief, digital Rx, chamber audio summary |
| **Hospitals** | Registered network, OPD + ER Code Blue documentation |
| **Labs** | Token workflow, less paper chase |
| **System** | Verified providers only, auditable consent |

---

## 8. Elevator pitch (full)

In Bangladesh, most patients still have no single, complete health record. So when treatment starts, doctors often miss earlier illnesses — and new medicines can clash with old problems, causing side effects and unsafe care.

From a **hospital perspective**, when a patient goes into **cardiac arrest**, an emergency **Code Blue team of 8–15 people** rushes in — but in that chaos it is hard to record who gave which medicine, when CPR was done, and when a shock was delivered. During normal consultations, patients also forget what the doctor said and why they must take each medicine.

**MedCore** solves this. Only **registered doctors** and **registered hospitals** can join the platform. Every patient’s care stays in one consent-protected record.

With **AI**, we can record what is given to the patient and keep clear clinical notes. In hospital emergencies, the resuscitation team’s actions are captured and organized in real time. Patients can see **what each medicine is for**. Doctors can open the patient’s **full previous history**, plus a short **AI-made summary** for quick decisions.

Even in the doctor’s chamber, if the conversation is recorded, **AI summarizes and saves it** — so the important discussion between doctor and patient is never lost.

**MedCore — one trusted record, safer treatment, for every patient in Bangladesh.**

---

## 9. Demo access (judges)

| Role | How to enter | Demo |
|------|----------------|------|
| Patient | `/patient/login.php` | `rahat@example.com` / `Demo123!` |
| Doctor | `/doctor/select-hospital.php` | ABC Hospital → verify → OTP `1234` |
| Hospital | `/hospital/login.php` | `admin@abc-hospital.com` / `Demo123!` |
| Lab | `/lab/login.php` | `lab@apexdiagnostics.com` / `Demo123!` |
| ICE | `/emergency.php?uid=PT-000001` | No login |

Universal consent / login OTP for demos: **`1234`**

---

## 10. Project team note

MedCore is designed as a **competition-ready national digital health prototype**: real multi-portal workflows, consent security, and AI features that address **hospital emergency documentation** and **patient understanding** — not only a static EHR form.

**Closing line for judges:**  
*Care that remembers for Bangladesh — only with the patient’s consent.*
