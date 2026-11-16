<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/views/portal/navs.php';

initSession();

$isHospital = isLoggedIn('HOSPITAL_ADMIN');
$isDoctor = isLoggedIn('DOCTOR');
if (!$isHospital && !$isDoctor) {
    $fromDoctor = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/doctor/');
    $login = $fromDoctor
        ? APP_URL . '/doctor/select-hospital.php'
        : APP_URL . '/hospital/login.php';
    $redirect = $fromDoctor ? '/doctor/code-blue.php' : '/hospital/code-blue.php';
    header('Location: ' . $login . '?redirect=' . urlencode($redirect));
    exit;
}

$userName = $_SESSION['name'] ?? ($isDoctor ? 'Doctor' : 'ER Team');
$hospitalName = $_SESSION['hospital_name'] ?? 'Hospital';

$portalRole = $isDoctor ? 'Doctor' : 'Hospital';
$portalRoleBn = 'Code Blue ACLS';
$portalUser = $userName;
$portalMeta = $hospitalName . ' · Resuscitation co-pilot';
$portalTitle = 'Code Blue assistant';
$portalSubtitle = 'AHA ACLS timers · voice log · automated run sheet';
$portalNav = $isDoctor ? mc_doctor_nav('code_blue') : mc_hospital_nav('code_blue');
$portalTopAction = '<button type="button" class="mc-btn mc-btn-danger mc-btn-sm" onclick="toggleCodeTimer()"><i class="bi bi-play-fill"></i> Start / pause</button>';

require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<div class="code-blue-container">
  <div class="cb-telemetry-grid">
    <div class="cb-card cb-time-card">
      <div class="cb-card-label"><i class="bi bi-stopwatch-fill"></i> Total code duration</div>
      <div class="cb-big-timer" id="totalTimer">00:00</div>
      <div class="cb-timer-controls">
        <button type="button" class="cb-btn cb-btn-start" id="btnStartCode" onclick="toggleCodeTimer()">
          <i class="bi bi-play-fill"></i> Start code
        </button>
        <button type="button" class="cb-btn cb-btn-pause" id="btnPauseCode" onclick="resetCodeTimer()">
          <i class="bi bi-arrow-counterclockwise"></i> Reset
        </button>
      </div>
    </div>

    <!-- 2-Minute CPR Cycle Countdown -->
    <div class="cb-card cb-cpr-card" id="cprCycleCard">
      <div class="cb-card-label"><i class="bi bi-heart-pulse-fill"></i> 2-Minute CPR Cycle</div>
      <div class="cb-cpr-display">
        <div class="cb-cpr-time" id="cprCountdown">02:00</div>
        <div class="cb-cpr-sub" id="cprStatusText">Cycle #1 Active</div>
      </div>
      <div class="cb-cpr-bar-wrap">
        <div class="cb-cpr-bar" id="cprProgressBar" style="width: 100%"></div>
      </div>
      <div class="cb-timer-controls" style="margin-top:0.6rem">
        <button type="button" class="cb-btn cb-btn-pulse" onclick="restartCprCycle()">
          <i class="bi bi-arrow-repeat"></i> Reset 2-Min
        </button>
        <button type="button" class="cb-btn cb-btn-metronome" id="btnMetronome" onclick="toggleMetronome()">
          <i class="bi bi-soundwave"></i> Metronome (110 BPM)
        </button>
      </div>
    </div>

    <!-- Epinephrine Interval -->
    <div class="cb-card cb-epi-card" id="epiCard">
      <div class="cb-card-label"><i class="bi bi-capsule"></i> Epinephrine Interval</div>
      <div class="cb-epi-display">
        <div class="cb-epi-time" id="epiTimer">--:--</div>
        <div class="cb-epi-sub" id="epiSubText">No dose given yet</div>
      </div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:0.4rem">
        AHA Guide: 1mg IV/IO every 3–5 mins
      </div>
      <button type="button" class="cb-btn cb-btn-action" style="margin-top:0.5rem" onclick="recordIntervention('MEDICATION', 'Epinephrine 1mg IV/IO given', {drug: 'Epinephrine', dose: '1mg', route: 'IV/IO'})">
        <i class="bi bi-plus-circle-fill"></i> Push 1mg Epi Now
      </button>
    </div>

    <!-- Shock & ROSC Counters -->
    <div class="cb-card cb-counter-card">
      <div class="cb-card-label"><i class="bi bi-lightning-charge-fill"></i> Defibrillation & Rhythm</div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.5rem">
        <div>
          <div style="font-size:1.8rem;font-weight:900;color:#dc2626" id="shockCount">0</div>
          <div style="font-size:0.75rem;font-weight:700;color:var(--muted)">SHOCKS DELIVERED</div>
        </div>
        <div>
          <div style="font-size:1.8rem;font-weight:900;color:#0f766e" id="cprCycleCount">0</div>
          <div style="font-size:0.75rem;font-weight:700;color:var(--muted)">CPR CYCLES</div>
        </div>
      </div>
      <button type="button" class="cb-btn cb-btn-danger" style="margin-top:0.75rem;width:100%" onclick="recordIntervention('SHOCK', 'Defibrillation Shock 200J Biphasic Delivered', {energy: '200J', rhythm: 'VF/pVT'})">
        <i class="bi bi-lightning-fill"></i> Deliver Shock (200J)
      </button>
    </div>
  </div>

  <!-- 2. AI VOICE-TO-TEXT CO-PILOT BAR -->
  <div class="cb-voice-panel">
    <div class="cb-voice-header">
      <div style="display:flex;align-items:center;gap:0.75rem">
        <div class="cb-voice-pulse" id="voicePulseIndicator"></div>
        <div>
          <h3 style="margin:0;font-size:1.05rem;color:#0f172a">AudioToText + Groq Co-Pilot</h3>
          <p style="margin:0.15rem 0 0;font-size:0.8rem;color:var(--muted)">
            Whisper অডিও→টেক্সট · Groq ইন্টারভেনশন sort/রেকর্ড · Pulse/Epi রিমাইন্ডার
          </p>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:0.5rem">
        <button type="button" class="mc-btn mc-btn-primary mc-btn-sm" id="btnVoiceToggle" onclick="toggleVoiceListening()">
          <i class="bi bi-mic-fill"></i> <span id="voiceBtnText">Start listening</span>
        </button>
      </div>
    </div>

    <div class="cb-transcript-box" id="transcriptBox">
      <span class="transcript-placeholder" id="transcriptPlaceholder">
        মাইক চালু করুন — টিম যে কথা বলবে (Epi, Shock, CPR, ROSC) Whisper ট্রান্সক্রাইব করে Groq সাজিয়ে ledger-এ লিখবে। অথবা নিচের simulate chips ব্যবহার করুন।
      </span>
      <span id="liveTranscriptText" style="color:#0f172a;font-weight:600"></span>
    </div>
    <p id="cbAsrEngine" style="margin:.45rem 0 0;font-size:.75rem;color:var(--muted)"></p>

    <!-- Quick Audio Simulation / Test Chips -->
    <div class="cb-sim-chips">
      <span style="font-size:0.75rem;font-weight:700;color:var(--muted);align-self:center">Simulate Speech:</span>
      <button type="button" class="cb-chip" onclick="simulateSpeech('Dr. Kabir administered Epinephrine 1 milligram IV push and flushed with 20ml saline')">
        "Epinephrine 1mg IV pushed"
      </button>
      <button type="button" class="cb-chip" onclick="simulateSpeech('Defibrillator charged to 200 Joules biphasic, clear all, shock delivered, resume chest compressions immediately')">
        "Shock 200J delivered & resume CPR"
      </button>
      <button type="button" class="cb-chip" onclick="simulateSpeech('Amiodarone 300 milligram IV bolus administered for refractory ventricular fibrillation')">
        "Amiodarone 300mg IV given"
      </button>
      <button type="button" class="cb-chip" onclick="simulateSpeech('Endotracheal intubation successful, size 7.5 ET tube secured at 22 centimeters, bilateral breath sounds confirmed')">
        "Intubation secured & confirmed"
      </button>
      <button type="button" class="cb-chip" onclick="simulateSpeech('Two minutes CPR complete, pause compressions. Rhythm check shows narrow complex, carotid pulse is present, ROSC confirmed!')">
        "ROSC Confirmed!"
      </button>
    </div>
  </div>

  <!-- 3. MAIN WORKFLOW: TIMELINE LEDGER + ACLS GUIDELINES -->
  <div class="cb-main-grid">
    <!-- Left: Chronological Event Ledger -->
    <div class="cb-card cb-ledger-card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
        <div>
          <h3 style="margin:0;font-size:1.15rem;color:#0f172a"><i class="bi bi-journal-medical"></i> Resuscitation Event Ledger</h3>
          <p style="margin:0.2rem 0 0;font-size:0.8rem;color:var(--muted)">AHA ACLS Real-Time Timestamped Log</p>
        </div>
        <div style="display:flex;gap:0.4rem">
          <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="exportLedgerCsv()">
            <i class="bi bi-download"></i> Export CSV
          </button>
          <button type="button" class="mc-btn mc-btn-primary mc-btn-sm" onclick="openAclsReportModal()">
            <i class="bi bi-file-earmark-text-fill"></i> Generate AHA Report
          </button>
        </div>
      </div>

      <!-- Quick Manual Action Pills (Backup for Noisy Rooms) -->
      <div class="cb-quick-actions">
        <button type="button" class="cb-q-btn red" onclick="recordIntervention('SHOCK', 'Defibrillation 200J Biphasic Shock Delivered')">⚡ Shock 200J</button>
        <button type="button" class="cb-q-btn purple" onclick="recordIntervention('MEDICATION', 'Epinephrine 1mg IV/IO Administered')">💉 Epi 1mg</button>
        <button type="button" class="cb-q-btn blue" onclick="recordIntervention('MEDICATION', 'Amiodarone 300mg IV Bolus Administered')">💊 Amio 300mg</button>
        <button type="button" class="cb-q-btn cyan" onclick="recordIntervention('AIRWAY', 'Endotracheal Intubation Completed & Capnography Attached')">🫁 Intubation ETT</button>
        <button type="button" class="cb-q-btn amber" onclick="recordIntervention('RHYTHM', 'Rhythm Check: Ventricular Fibrillation (VF) Confirmed')">🩸 Rhythm: VF/VT</button>
        <button type="button" class="cb-q-btn teal" onclick="recordIntervention('RHYTHM', 'Rhythm Check: Asystole / PEA (Non-Shockable)')">⚠️ Asystole/PEA</button>
        <button type="button" class="cb-q-btn green" onclick="recordIntervention('ROSC', 'Return of Spontaneous Circulation (ROSC) Achieved!')">🟢 ROSC Achieved</button>
      </div>

      <!-- Timeline List -->
      <div class="cb-timeline-list" id="timelineList">
        <div class="cb-empty-ledger" id="emptyLedger">
          <i class="bi bi-clipboard2-pulse"></i>
          <div>No resuscitation events recorded yet.</div>
          <p>Start the code timer or speak into the microphone to log events.</p>
        </div>
      </div>
    </div>

    <!-- Right: AHA ACLS 5 H's and 5 T's Differential Diagnostic Assistant -->
    <div class="cb-side-column">
      <div class="cb-card cb-guide-card">
        <h4 style="margin:0 0 0.5rem;font-size:0.95rem;color:#0f172a;display:flex;align-items:center;gap:0.4rem">
          <i class="bi bi-shield-check" style="color:var(--accent)"></i> AHA ACLS Algorithm Rules
        </h4>
        <ul class="cb-acls-rules">
          <li><strong>Rate:</strong> 100–120 compressions/min, depth 2–2.4 inches (5–6 cm).</li>
          <li><strong>Minimize Pauses:</strong> Pre-charge defibrillator prior to 2-min pause.</li>
          <li><strong>Epi Timing:</strong> Give Epinephrine ASAP in PEA/Asystole; after 2nd shock in VF/pVT.</li>
          <li><strong>Amiodarone:</strong> 1st dose 300mg after 3rd shock; 2nd dose 150mg.</li>
        </ul>
      </div>

      <div class="cb-card" style="margin-top:1rem">
        <h4 style="margin:0 0 0.75rem;font-size:0.95rem;color:#0f172a">
          <i class="bi bi-search"></i> Reversible Causes (5 H's & 5 T's)
        </h4>
        <div class="cb-causes-grid">
          <div>
            <strong style="display:block;font-size:0.8rem;color:#dc2626;margin-bottom:0.35rem">THE 5 H's:</strong>
            <label class="cb-check-item"><input type="checkbox" onchange="toggleCause(this, 'Hypovolemia')"> Hypovolemia</label>
            <label class="cb-check-item"><input type="checkbox" onchange="toggleCause(this, 'Hypoxia')"> Hypoxia</label>
            <label class="cb-check-item"><input type="checkbox" onchange="toggleCause(this, 'Hydrogen Ion (Acidosis)')"> Hydrogen Ion (Acidosis)</label>
            <label class="cb-check-item"><input type="checkbox" onchange="toggleCause(this, 'Hypo/Hyperkalemia')"> Hypo/Hyperkalemia</label>
            <label class="cb-check-item"><input type="checkbox" onchange="toggleCause(this, 'Hypothermia')"> Hypothermia</label>
          </div>
          <div>
            <strong style="display:block;font-size:0.8rem;color:#2563eb;margin-bottom:0.35rem">THE 5 T's:</strong>
            <label class="cb-check-item"><input type="checkbox" onchange="toggleCause(this, 'Tension Pneumothorax')"> Tension Pneumothorax</label>
            <label class="cb-check-item"><input type="checkbox" onchange="toggleCause(this, 'Tamponade (Cardiac)')"> Tamponade (Cardiac)</label>
            <label class="cb-check-item"><input type="checkbox" onchange="toggleCause(this, 'Toxins')"> Toxins</label>
            <label class="cb-check-item"><input type="checkbox" onchange="toggleCause(this, 'Thrombosis (Pulmonary)')"> Thrombosis (PE)</label>
            <label class="cb-check-item"><input type="checkbox" onchange="toggleCause(this, 'Thrombosis (Coronary)')"> Thrombosis (MI)</label>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- AHA ACLS REPORT MODAL -->
<div class="mc-modal-overlay" id="aclsReportModal" style="display:none">
  <div class="mc-modal" style="max-width:850px;width:95%">
    <div class="mc-modal-header" style="display:flex;justify-content:space-between;align-items:center;padding:1.25rem 1.5rem;border-bottom:1px solid var(--line)">
      <div style="display:flex;align-items:center;gap:0.6rem">
        <div style="width:36px;height:36px;border-radius:10px;background:#ecfdf8;color:#0f766e;display:grid;place-items:center">
          <i class="bi bi-file-earmark-medical-fill" style="font-size:1.25rem"></i>
        </div>
        <div>
          <h3 style="margin:0;font-size:1.15rem;color:#0f172a">AHA ACLS Official Resuscitation Run Sheet</h3>
          <span style="font-size:0.8rem;color:var(--muted)">Legal-Grade Medical Audit & Clinical Documentation</span>
        </div>
      </div>
      <button type="button" class="mc-close-btn" onclick="closeAclsReportModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer">&times;</button>
    </div>
    <div class="mc-modal-body" style="padding:1.5rem;max-height:70vh;overflow-y:auto">
      <div id="aclsReportLoading" style="text-align:center;padding:2rem">
        <div class="spinner-border text-primary" role="status"></div>
        <p style="margin-top:0.75rem;font-weight:700;color:var(--accent)">
          Groq AI analyzing resuscitation timeline against AHA ACLS guidelines...
        </p>
      </div>
      <div id="aclsReportContent" style="display:none;font-size:0.9rem;line-height:1.65;color:#1e293b"></div>
    </div>
    <div class="mc-modal-footer" style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.5rem;background:#f8fafc;border-top:1px solid var(--line)">
      <div style="font-size:0.8rem;color:var(--muted)">
        <i class="bi bi-patch-check-fill" style="color:#0f766e"></i> DGHS Hospital Resuscitation Audit Compliant
      </div>
      <div style="display:flex;gap:0.5rem">
        <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="copyReportText()">
          <i class="bi bi-clipboard"></i> Copy Text
        </button>
        <button type="button" class="mc-btn mc-btn-primary mc-btn-sm" onclick="window.print()">
          <i class="bi bi-printer-fill"></i> Print / Save PDF
        </button>
        <button type="button" class="mc-btn mc-btn-ghost mc-btn-sm" onclick="closeAclsReportModal()">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
// Code Blue State & Timers
let codeRunning = false;
let codeSeconds = 0;
let codeTimerInterval = null;

let cprSecondsRemaining = 120;
let cprCycleCount = 0;
let cprInterval = null;

let epiSeconds = 0;
let epiActive = false;
let epiInterval = null;

let totalShocks = 0;
let eventsLedger = [];

// Metronome Audio
let metronomeActive = false;
let metronomeInterval = null;
let audioCtx = null;

// Speech Recognition
let recognition = null;
let isListening = false;

function formatMMSS(s) {
  const m = Math.floor(s / 60);
  const rem = s % 60;
  return String(m).padStart(2, '0') + ':' + String(rem).padStart(2, '0');
}

function toggleCodeTimer() {
  const btn = document.getElementById('btnStartCode');
  if (!codeRunning) {
    codeRunning = true;
    btn.innerHTML = '<i class="bi bi-pause-fill"></i> Pause Code';
    btn.style.background = '#b45309';

    codeTimerInterval = setInterval(() => {
      codeSeconds++;
      document.getElementById('totalTimer').textContent = formatMMSS(codeSeconds);
    }, 1000);

    startCprCycle();
    recordIntervention('CPR', 'Code Blue Initiated — CPR Cycle #1 Started');
  } else {
    codeRunning = false;
    btn.innerHTML = '<i class="bi bi-play-fill"></i> Resume Code';
    btn.style.background = '#0f766e';
    clearInterval(codeTimerInterval);
    clearInterval(cprInterval);
  }
}

function resetCodeTimer() {
  if (confirm("Reset current resuscitation timers and ledger?")) {
    clearInterval(codeTimerInterval);
    clearInterval(cprInterval);
    clearInterval(epiInterval);
    if (metronomeActive) toggleMetronome();
    codeRunning = false;
    codeSeconds = 0;
    cprSecondsRemaining = 120;
    cprCycleCount = 0;
    totalShocks = 0;
    eventsLedger = [];
    document.getElementById('totalTimer').textContent = '00:00';
    document.getElementById('cprCountdown').textContent = '02:00';
    document.getElementById('cprProgressBar').style.width = '100%';
    document.getElementById('cprCycleCount').textContent = '0';
    document.getElementById('shockCount').textContent = '0';
    document.getElementById('btnStartCode').innerHTML = '<i class="bi bi-play-fill"></i> Start Code';
    document.getElementById('btnStartCode').style.background = '#0f766e';
    renderLedger();
  }
}

function startCprCycle() {
  clearInterval(cprInterval);
  cprSecondsRemaining = 120;
  cprCycleCount++;
  document.getElementById('cprCycleCount').textContent = cprCycleCount;
  document.getElementById('cprStatusText').textContent = 'Cycle #' + cprCycleCount + ' Active';

  cprInterval = setInterval(() => {
    if (!codeRunning) return;
    cprSecondsRemaining--;
    document.getElementById('cprCountdown').textContent = formatMMSS(cprSecondsRemaining);
    const pct = (cprSecondsRemaining / 120) * 100;
    document.getElementById('cprProgressBar').style.width = pct + '%';

    if (cprSecondsRemaining <= 0) {
      clearInterval(cprInterval);
      triggerPulseCheckAlarm();
    }
  }, 1000);
}

function restartCprCycle() {
  startCprCycle();
  recordIntervention('CPR', 'CPR Cycle #' + cprCycleCount + ' manually restarted');
}

function triggerPulseCheckAlarm() {
  // Audible voice alert
  speakVoiceAlert("Two minutes elapsed. Pause compressions. Check pulse and analyze rhythm now.");
  document.getElementById('cprCountdown').textContent = 'PULSE CHECK!';
  document.getElementById('cprCountdown').style.color = '#dc2626';
  recordIntervention('PULSE_CHECK', '2-Minute Cycle Complete — Rhythm & Pulse Check Prompted');

  setTimeout(() => {
    document.getElementById('cprCountdown').style.color = '#1d4ed8';
    startCprCycle();
  }, 10000);
}

function startEpiTimer() {
  clearInterval(epiInterval);
  epiSeconds = 0;
  epiActive = true;
  document.getElementById('epiSubText').textContent = 'Last dose just given';

  epiInterval = setInterval(() => {
    epiSeconds++;
    document.getElementById('epiTimer').textContent = formatMMSS(epiSeconds);

    if (epiSeconds >= 180 && epiSeconds < 300) {
      document.getElementById('epiSubText').innerHTML = '<span style="color:#b45309;font-weight:700">Due soon (3m elapsed)</span>';
    } else if (epiSeconds >= 300) {
      document.getElementById('epiSubText').innerHTML = '<span style="color:#dc2626;font-weight:700">DUE NOW (>= 5m elapsed)</span>';
      if (epiSeconds === 300) {
        speakVoiceAlert("Attention resuscitation team: Epinephrine is due now.");
      }
    }
  }, 1000);
}

// Metronome Audio Generator (110 BPM)
function toggleMetronome() {
  const btn = document.getElementById('btnMetronome');
  if (!metronomeActive) {
    metronomeActive = true;
    btn.classList.add('active');
    btn.innerHTML = '<i class="bi bi-volume-up-fill"></i> Metronome ON (110)';

    try {
      if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    } catch(e) {}

    const intervalMs = (60 / 110) * 1000;
    metronomeInterval = setInterval(() => {
      playBeep();
    }, intervalMs);
  } else {
    metronomeActive = false;
    btn.classList.remove('active');
    btn.innerHTML = '<i class="bi bi-soundwave"></i> Metronome (110 BPM)';
    clearInterval(metronomeInterval);
  }
}

function playBeep() {
  if (!audioCtx) return;
  try {
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.connect(gain);
    gain.connect(audioCtx.destination);
    osc.frequency.value = 880;
    gain.gain.value = 0.15;
    osc.start();
    gain.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + 0.05);
    osc.stop(audioCtx.currentTime + 0.06);
  } catch(e) {}
}

function speakVoiceAlert(text) {
  if ('speechSynthesis' in window) {
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.rate = 1.05;
    utterance.pitch = 1.0;
    window.speechSynthesis.speak(utterance);
  }
}

// Record an intervention
function recordIntervention(type, title, details = {}) {
  if (!codeRunning && codeSeconds === 0) {
    toggleCodeTimer();
  }

  const timeStr = formatMMSS(codeSeconds);
  const item = {
    id: Date.now(),
    time: timeStr,
    type: type,
    title: title,
    details: details
  };

  eventsLedger.unshift(item);

  if (type === 'SHOCK') {
    totalShocks++;
    document.getElementById('shockCount').textContent = totalShocks;
  }
  if (type === 'MEDICATION' && title.includes('Epi')) {
    startEpiTimer();
  }

  renderLedger();
}

function renderLedger() {
  const container = document.getElementById('timelineList');
  if (eventsLedger.length === 0) {
    container.innerHTML = `
      <div class="cb-empty-ledger" id="emptyLedger">
        <i class="bi bi-clipboard2-pulse"></i>
        <div>No resuscitation events recorded yet.</div>
        <p>Start the code timer or speak into the microphone to log events.</p>
      </div>
    `;
    return;
  }

  container.innerHTML = eventsLedger.map(ev => {
    let cls = '';
    if (ev.type === 'SHOCK') cls = 'shock';
    if (ev.type === 'MEDICATION') cls = 'med';
    if (ev.type === 'ROSC') cls = 'rosc';

    return `
      <div class="cb-timeline-item ${cls}">
        <div style="display:flex;align-items:center;gap:0.75rem">
          <span class="cb-time-badge">${ev.time}</span>
          <div>
            <strong style="display:block;font-size:0.92rem;color:#0f172a">${ev.title}</strong>
            <span style="font-size:0.78rem;color:var(--muted)">${ev.type} · Logged by ACLS Assistant</span>
          </div>
        </div>
        <button type="button" onclick="deleteEvent(${ev.id})" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:0.9rem" title="Remove">&times;</button>
      </div>
    `;
  }).join('');
}

function deleteEvent(id) {
  eventsLedger = eventsLedger.filter(e => e.id !== id);
  renderLedger();
}

function toggleCause(checkbox, cause) {
  if (checkbox.checked) {
    recordIntervention('REVERSIBLE_CAUSE', `Assessment: ${cause} evaluated / managed`);
  }
}

// -------------------------------------------------------------
// AudioToText (Whisper) → Groq ACLS parse/sort
// -------------------------------------------------------------
let cbAudioClient = null;

function toggleVoiceListening() {
  const btn = document.getElementById('btnVoiceToggle');
  const btnText = document.getElementById('voiceBtnText');
  const pulse = document.getElementById('voicePulseIndicator');
  const engineEl = document.getElementById('cbAsrEngine');

  if (!isListening) {
    if (!window.MedCoreAudioToText) {
      alert('AudioToText client missing. Use quick actions or simulate chips.');
      return;
    }
    cbAudioClient = MedCoreAudioToText.create({
      endpoint: '<?= APP_URL ?>/api/audio-transcribe.php',
      mode: 'code_blue',
      chunkMs: 4500,
      onPartial: function (t) {
        document.getElementById('transcriptPlaceholder').style.display = 'none';
        document.getElementById('liveTranscriptText').textContent = t;
      },
      onFinal: function (t) {
        document.getElementById('transcriptPlaceholder').style.display = 'none';
        document.getElementById('liveTranscriptText').textContent = t;
        if (engineEl) engineEl.style.color = '';
        processTranscriptWithGroq(t);
      },
      onStatus: function (s) {
        if (!engineEl) return;
        if (s === 'whisper') {
          engineEl.style.color = '';
          engineEl.textContent = 'Engine: Whisper (AudioToText) → Groq refine + ACLS sort · speak clearly, text appears every ~4–5s';
        } else if (s === 'uploading') {
          engineEl.style.color = '';
          engineEl.textContent = 'Uploading audio segment to Whisper…';
        } else if (s === 'webspeech') {
          engineEl.style.color = '';
          engineEl.textContent = 'Engine: Web Speech fallback → Groq';
        }
      },
      onError: function (msg) {
        console.warn('ASR:', msg);
        if (engineEl) {
          engineEl.style.color = '#b42318';
          engineEl.textContent = 'ASR: ' + msg;
        }
      }
    });
    cbAudioClient.start().then(function () {
      isListening = true;
      btnText.textContent = 'Listening…';
      btn.classList.add('mc-btn-danger');
      pulse.classList.add('listening');
      document.getElementById('transcriptPlaceholder').style.display = 'none';
      if (engineEl) engineEl.textContent = 'Listening… allow mic, then speak (Epi / Shock / CPR).';
    }).catch(function (err) {
      alert('Microphone permission required for AudioToText. ' + (err && err.message ? err.message : ''));
    });
  } else {
    isListening = false;
    if (cbAudioClient) cbAudioClient.stop();
    if (recognition) recognition.stop();
    btnText.textContent = 'Start listening';
    btn.classList.remove('mc-btn-danger');
    pulse.classList.remove('listening');
  }
}

// Process speech transcript with Groq API
async function processTranscriptWithGroq(text) {
  try {
    const res = await fetch('<?= APP_URL ?>/api/code-blue.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'parse_speech',
        transcript: text
      })
    });
    const data = await res.json();
    if (data.success && data.events && data.events.length > 0) {
      data.events.forEach(ev => {
        recordIntervention(ev.type, ev.title, ev);
      });
      if (data.next_acls_reminder) {
        speakVoiceAlert(data.next_acls_reminder);
      }
    }
  } catch(e) {
    console.error("Groq AI parse failed:", e);
  }
}

// Simulate speech chip
function simulateSpeech(text) {
  document.getElementById('transcriptPlaceholder').style.display = 'none';
  document.getElementById('liveTranscriptText').textContent = '"' + text + '"';
  processTranscriptWithGroq(text);
}

// -------------------------------------------------------------
// AHA ACLS Report Generation via Groq AI
// -------------------------------------------------------------
async function openAclsReportModal() {
  const modal = document.getElementById('aclsReportModal');
  const loading = document.getElementById('aclsReportLoading');
  const content = document.getElementById('aclsReportContent');

  modal.style.display = 'grid';
  loading.style.display = 'block';
  content.style.display = 'none';

  const sessionData = {
    patient: 'PT-000001 (Rafeez Uddin - Age 58)',
    location: '<?= htmlspecialchars($hospitalName) ?> — ICU Resuscitation Bay',
    start_time: new Date().toLocaleString(),
    duration_minutes: Math.ceil(codeSeconds / 60) || 12,
    outcome: eventsLedger.some(e => e.type === 'ROSC') ? 'ROSC Achieved (Post-Cardiac Arrest Protocol Activated)' : 'In Progress / Ongoing Resuscitation',
    events: eventsLedger
  };

  try {
    const res = await fetch('<?= APP_URL ?>/api/code-blue.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'generate_report',
        session_data: sessionData
      })
    });
    const data = await res.json();
    loading.style.display = 'none';
    content.style.display = 'block';

    if (data.success && data.report_markdown) {
      content.innerHTML = formatMarkdown(data.report_markdown);
      window.lastAclsReportText = data.report_markdown;
    } else {
      content.innerHTML = '<p class="text-danger">Failed to generate report.</p>';
    }
  } catch(e) {
    loading.style.display = 'none';
    content.style.display = 'block';
    content.innerHTML = '<p class="text-danger">Network error connecting to Groq AI.</p>';
  }
}

function closeAclsReportModal() {
  document.getElementById('aclsReportModal').style.display = 'none';
}

function copyReportText() {
  if (window.lastAclsReportText) {
    navigator.clipboard.writeText(window.lastAclsReportText);
    alert("Report copied to clipboard!");
  }
}

function exportLedgerCsv() {
  if (eventsLedger.length === 0) {
    alert("Ledger is empty.");
    return;
  }
  let csv = "Elapsed Time,Type,Title\n";
  eventsLedger.forEach(e => {
    csv += `"${e.time}","${e.type}","${e.title.replace(/"/g, '""')}"\n`;
  });
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `Code_Blue_RunSheet_${Date.now()}.csv`;
  a.click();
}

// Simple markdown formatter
function formatMarkdown(md) {
  let html = md
    .replace(/^# (.*$)/gim, '<h2 style="font-size:1.3rem;color:#0f172a;margin:1rem 0 .5rem">$1</h2>')
    .replace(/^### (.*$)/gim, '<h4 style="font-size:1.05rem;color:#0f766e;margin:1.2rem 0 .4rem">$1</h4>')
    .replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/gim, '<em>$1</em>')
    .replace(/`([^`]+)`/gim, '<code class="mc-mono" style="background:#e2e8f0;padding:2px 5px;border-radius:4px">$1</code>')
    .replace(/\n\n/gim, '<br><br>');
  return html;
}
</script>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
