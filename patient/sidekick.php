<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/views/portal/navs.php';

initSession();

$isPatient = isLoggedIn('PATIENT');
$patientUid = $_SESSION['patient_uid'] ?? 'PT-000001';
$patientName = $_SESSION['name'] ?? 'Rahat Mahamud';

$portalRole = 'Patient';
$portalRoleBn = 'নাগরিক ও রোগী পোর্টাল';
$portalUser = $patientName;
$portalMeta = 'Patient UID: ' . $patientUid;
$portalTitle = 'Sidekick — Real-Time Patient Advocate';
$portalSubtitle = 'ডাক্তারের সাথে অ্যাপয়েন্টমেন্টের সময় লাইভ ভয়েস অনুবাদ, মেডিকেল টার্ম সহজ ব্যাখ্যা ও করণীয় সারাংশ';
$portalNav = mc_patient_nav('sidekick');

require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<div class="sidekick-container">
  <!-- 1. TOP ADVOCATE HERO BANNER -->
  <div class="sk-banner">
    <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
      <div class="sk-banner-icon"><i class="bi bi-shield-heart-fill"></i></div>
      <div style="flex:1;min-width:260px">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem">
          <span class="mc-pill" style="background:#0f766e;color:#fff;font-size:0.75rem;border:none">
            <i class="bi bi-stars"></i> Real-Time AI Advocate
          </span>
          <span style="font-size:0.8rem;color:#64748b;font-weight:600">Patient Rights & Clarification</span>
        </div>
        <h2 style="margin:0;font-size:1.35rem;color:#0f172a">কখনো কোনো চিকিৎসা তথ্য ভুলে যাবেন না</h2>
        <p style="margin:0.25rem 0 0;font-size:0.88rem;color:var(--muted)">
          ডাক্তারের রুমে এটি চালু রাখুন — জটিল মেডিকেল শব্দ সহজ বাংলায় বুঝে নিন এবং সঠিক প্রশ্ন করার তাৎক্ষণিক পরামর্শ পান।
        </p>
      </div>
      <div>
        <button type="button" class="mc-btn mc-btn-primary mc-btn-lg" id="btnMicToggle" onclick="toggleSidekickListening()">
          <i class="bi bi-mic-fill"></i> <span id="micBtnLabel">Start Listening (ভয়েস রেকর্ড চালু করুন)</span>
        </button>
      </div>
    </div>
  </div>

  <!-- 2. LIVE CONSULTATION AUDIO TRANSCRIPT -->
  <div class="sk-transcript-panel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem">
      <div style="display:flex;align-items:center;gap:0.5rem">
        <span class="sk-pulse-dot" id="skPulse"></span>
        <strong style="font-size:0.92rem;color:#0f172a">লাইভ কথোপকথন (Live Consultation Speech)</strong>
      </div>
      <span style="font-size:0.78rem;color:var(--muted)">Web Speech API & Groq Whisper AI</span>
    </div>

    <div class="sk-speech-box" id="speechBox">
      <span class="sk-placeholder" id="speechPlaceholder">
        "Start Listening" বোতামে চাপ দিন এবং স্বাভাবিকভাবে ডাক্তারের সাথে কথা বলুন। অথবা নিচের ডেমো অডিও টেস্ট বাটনে ক্লিক করে পরীক্ষা করুন...
      </span>
      <span id="speechLiveText" style="color:#0f172a;font-weight:600"></span>
    </div>

    <!-- Quick Simulation Chips -->
    <div class="sk-sim-chips">
      <span style="font-size:0.78rem;font-weight:700;color:var(--muted);align-self:center">ডেমো সিমুলেশন:</span>
      <button type="button" class="sk-chip" onclick="simulateDoctorSpeech('আপনার রক্তচাপ ১৪৫/৯৫ এসেছে, যা হাইপারটেনশন। আপনাকে নিয়মিত সকালে একটা করে ট্যাবলেট এমলোডিপিন ৫ মিগ্রা খেতে হবে এবং খাবারে কাঁচা লবণ বন্ধ করতে হবে।')">
        "হাইপারটেনশন ও রক্তচাপের ওষুধ"
      </button>
      <button type="button" class="sk-chip" onclick="simulateDoctorSpeech('ইসিজি রিপোর্টে কিছুটা হালকা ইস্কেমিয়া দেখা যাচ্ছে। আমরা নিশ্চিত হতে একটা ইকোকার্ডিওগ্রাম এবং ট্রপোনিন আই টেস্ট করব। কোনো ভারী কাজ করবেন না।')">
        "ইসিজি ও ইকোকার্ডিওগ্রাম টেস্ট"
      </button>
      <button type="button" class="sk-chip" onclick="simulateDoctorSpeech('রক্তে কোলেস্টেরল বেশি, তাই একটা স্ট্যাটিন ওষুধ দিচ্ছি। প্রতিদিন রাতে খাবারের পর খাবেন। সাথে এই অ্যান্টিবায়োটিক ৭ দিন নিয়মিত শেষ করবেন।')">
        "অ্যান্টিবায়োটিক ও কোলেস্টেরল"
      </button>
    </div>
  </div>

  <!-- 3. REAL-TIME SPLIT: JARGON BUSTER & PROACTIVE QUESTIONS -->
  <div class="sk-main-grid">
    <!-- Left: Jargon Buster (Medical Terms Simplified) -->
    <div class="mc-card">
      <div class="sk-card-head">
        <div style="display:flex;align-items:center;gap:0.5rem">
          <div class="sk-icon-wrap teal"><i class="bi bi-translate"></i></div>
          <div>
            <h3 style="margin:0;font-size:1.1rem;color:#0f172a">Medical Terms Jargon Buster</h3>
            <p style="margin:0.15rem 0 0;font-size:0.78rem;color:var(--muted)">জটিল ডাক্তারি পরিভাষা সহজ বাংলা ও ইংরেজিতে</p>
          </div>
        </div>
        <span class="sk-count-badge" id="termsCount">0 terms</span>
      </div>

      <div class="sk-terms-list" id="termsList">
        <div class="sk-empty-hint" id="termsEmpty">
          <i class="bi bi-book"></i>
          <div>কোনো মেডিকেল শব্দ শনাক্ত হয়নি</div>
          <p>ডাক্তার যখন কোনো রোগ বা টেস্টের নাম বলবেন, এখানে তার সহজ ব্যাখ্যা ফুটে উঠবে।</p>
        </div>
      </div>
    </div>

    <!-- Right: Smart Questions To Ask The Doctor -->
    <div class="mc-card">
      <div class="sk-card-head">
        <div style="display:flex;align-items:center;gap:0.5rem">
          <div class="sk-icon-wrap amber"><i class="bi bi-question-circle-fill"></i></div>
          <div>
            <h3 style="margin:0;font-size:1.1rem;color:#0f172a">ডাক্তারকে যা জিজ্ঞেস করবেন</h3>
            <p style="margin:0.15rem 0 0;font-size:0.78rem;color:var(--muted)">সচেতন রোগীর জন্য স্বয়ংক্রিয় প্রশ্ন সাজেশন</p>
          </div>
        </div>
        <span class="sk-count-badge" id="questionsCount">0 questions</span>
      </div>

      <div class="sk-questions-list" id="questionsList">
        <div class="sk-empty-hint" id="questionsEmpty">
          <i class="bi bi-chat-quote"></i>
          <div>সাজেশন অপেক্ষায় আছে</div>
          <p>কথোপকথন শুরু হলে এআই প্রাসঙ্গিক প্রশ্ন তৈরি করে দেবে যাতে আপনি কোনো গুরুত্বপূর্ণ তথ্য জানতে না ভুলেন।</p>
        </div>
      </div>
    </div>
  </div>

  <!-- 4. BOTTOM ACTION: GENERATE CONSULTATION SUMMARY & NEXT STEPS -->
  <div class="sk-summary-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
      <div>
        <h3 style="margin:0;font-size:1.15rem;color:#0f172a">অ্যাপয়েন্টমেন্ট শেষ? সারাংশ ও করণীয় তৈরি করুন</h3>
        <p style="margin:0.25rem 0 0;font-size:0.85rem;color:var(--muted)">
          পুরো সাক্ষাতের আলোচনা থেকে প্রেসক্রিপশন, টেস্ট এবং পরবর্তী করণীয় তালিকা এক ক্লিকে তৈরি করুন।
        </p>
      </div>
      <div style="display:flex;gap:0.5rem">
        <button type="button" class="mc-btn mc-btn-primary mc-btn-lg" onclick="generateConsultationSummary()">
          <i class="bi bi-magic"></i> Generate Summary & Next Steps
        </button>
      </div>
    </div>

    <!-- Generated Summary Display -->
    <div id="summaryResultArea" style="display:none;margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--line)">
      <div id="summaryLoading" style="text-align:center;padding:1.5rem">
        <div class="spinner-border text-primary" role="status"></div>
        <p style="margin-top:0.5rem;font-weight:700;color:var(--accent)">Groq AI আপনার পরামর্শের সম্পূর্ণ সারাংশ তৈরি করছে...</p>
      </div>
      <div id="summaryBody" style="font-size:0.92rem;line-height:1.65;color:#1e293b"></div>
      <div style="display:flex;gap:0.5rem;margin-top:1rem;justify-content:flex-end">
        <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="copySummaryText()">
          <i class="bi bi-clipboard"></i> Copy Summary
        </button>
        <button type="button" class="mc-btn mc-btn-primary mc-btn-sm" onclick="window.print()">
          <i class="bi bi-printer"></i> Print / Save
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.sidekick-container { display: flex; flex-direction: column; gap: 1.25rem; }
.sk-banner { background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf8 50%, #eff6ff 100%); border: 1.5px solid #a7f3d0; border-radius: 18px; padding: 1.5rem; box-shadow: 0 4px 12px rgba(16,24,40,0.03); }
.sk-banner-icon { width: 56px; height: 56px; border-radius: 16px; background: #0f766e; color: #fff; display: grid; place-items: center; font-size: 1.8rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(15,118,110,0.25); }

.sk-transcript-panel { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
.sk-pulse-dot { width: 10px; height: 10px; border-radius: 50%; background: #94a3b8; }
.sk-pulse-dot.active { background: #ef4444; box-shadow: 0 0 10px #ef4444; animation: pulse-sk 1.5s infinite; }
@keyframes pulse-sk { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.25);opacity:0.6} }
.sk-speech-box { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 1rem; min-height: 56px; font-size: 0.95rem; line-height: 1.55; margin-bottom: 0.85rem; }
.sk-placeholder { color: #94a3b8; font-style: italic; }
.sk-sim-chips { display: flex; flex-wrap: wrap; gap: 0.45rem; }
.sk-chip { background: #fff; border: 1px solid #cbd5e1; border-radius: 20px; padding: 0.3rem 0.75rem; font-size: 0.78rem; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.15s ease; }
.sk-chip:hover { border-color: #0f766e; background: #ecfdf8; color: #0f766e; }

.sk-main-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
@media (max-width: 900px) { .sk-main-grid { grid-template-columns: 1fr; } }
.sk-card-head { display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.85rem; margin-bottom: 1rem; border-bottom: 1px solid var(--line); }
.sk-icon-wrap { width: 40px; height: 40px; border-radius: 11px; display: grid; place-items: center; font-size: 1.2rem; flex-shrink: 0; }
.sk-icon-wrap.teal { background: #ecfdf8; color: #0f766e; }
.sk-icon-wrap.amber { background: #fffbeb; color: #b45309; }
.sk-count-badge { font-size: 0.75rem; font-weight: 700; background: #f1f5f9; padding: 0.2rem 0.55rem; border-radius: 999px; color: #64748b; }

.sk-terms-list, .sk-questions-list { display: flex; flex-direction: column; gap: 0.75rem; min-height: 220px; max-height: 460px; overflow-y: auto; }
.sk-empty-hint { text-align: center; padding: 2.5rem 1rem; color: var(--muted); }
.sk-empty-hint i { font-size: 2rem; display: block; margin-bottom: 0.4rem; opacity: 0.45; }

.sk-term-card { background: #fafbfc; border: 1px solid #e2e8f0; border-left: 4px solid #0f766e; border-radius: 12px; padding: 0.85rem 1rem; }
.sk-term-word { font-size: 0.95rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem; }
.sk-term-bn { font-size: 0.86rem; color: #0f766e; font-weight: 600; line-height: 1.45; margin-bottom: 0.2rem; }
.sk-term-en { font-size: 0.78rem; color: #64748b; line-height: 1.4; }

.sk-question-card { background: #fffdf5; border: 1px solid #fef3c7; border-left: 4px solid #d97706; border-radius: 12px; padding: 0.85rem 1rem; position: relative; }
.sk-question-text { font-size: 0.9rem; font-weight: 700; color: #92400e; line-height: 1.45; margin-bottom: 0.25rem; }
.sk-question-reason { font-size: 0.78rem; color: #b45309; }

.sk-summary-card { background: #ffffff; border: 1px solid var(--line); border-radius: 18px; padding: 1.5rem; box-shadow: 0 4px 12px rgba(16,24,40,0.04); }
</style>

<script>
let isListening = false;
let recognition = null;
let collectedTranscripts = [];
let detectedTerms = [];
let suggestedQuestions = [];

function toggleSidekickListening() {
  if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
    alert("Web Speech API is not supported in this browser. Please use Chrome/Edge or click the Demo Simulation buttons.");
    return;
  }

  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  const btn = document.getElementById('btnMicToggle');
  const label = document.getElementById('micBtnLabel');
  const pulse = document.getElementById('skPulse');

  if (!isListening) {
    recognition = new SpeechRecognition();
    recognition.continuous = true;
    recognition.interimResults = true;
    recognition.lang = 'bn-BD'; // Support Bengali, falls back to English

    recognition.onstart = function() {
      isListening = true;
      label.textContent = 'Listening Active (রেকর্ড হচ্ছে)...';
      btn.classList.add('mc-btn-danger');
      pulse.classList.add('active');
      document.getElementById('speechPlaceholder').style.display = 'none';
    };

    recognition.onresult = function(event) {
      let interim = '';
      let finalTranscript = '';

      for (let i = event.resultIndex; i < event.results.length; ++i) {
        if (event.results[i].isFinal) {
          finalTranscript += event.results[i][0].transcript;
        } else {
          interim += event.results[i][0].transcript;
        }
      }

      document.getElementById('speechLiveText').textContent = finalTranscript || interim;

      if (finalTranscript.trim().length > 0) {
        collectedTranscripts.push(finalTranscript.trim());
        analyzeSnippetWithGroq(finalTranscript.trim());
      }
    };

    recognition.onerror = function(err) {
      console.warn("Sidekick mic error:", err);
    };

    recognition.onend = function() {
      if (isListening) {
        try { recognition.start(); } catch(e) {}
      }
    };

    try { recognition.start(); } catch(e) {}
  } else {
    isListening = false;
    if (recognition) recognition.stop();
    label.textContent = 'Start Listening (ভয়েস রেকর্ড চালু করুন)';
    btn.classList.remove('mc-btn-danger');
    pulse.classList.remove('active');
  }
}

async function analyzeSnippetWithGroq(text) {
  try {
    const res = await fetch('<?= APP_URL ?>/api/sidekick.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'analyze_speech',
        speech_text: text
      })
    });
    const data = await res.json();
    if (data.success) {
      if (data.terms && data.terms.length > 0) {
        data.terms.forEach(t => {
          if (!detectedTerms.some(existing => existing.term.toLowerCase() === t.term.toLowerCase())) {
            detectedTerms.unshift(t);
          }
        });
        renderTerms();
      }
      if (data.suggested_questions && data.suggested_questions.length > 0) {
        data.suggested_questions.forEach(q => {
          if (!suggestedQuestions.some(existing => existing.question_bn === q.question_bn)) {
            suggestedQuestions.unshift(q);
          }
        });
        renderQuestions();
      }
    }
  } catch(e) {
    console.error("Sidekick AI parse failed:", e);
  }
}

function simulateDoctorSpeech(text) {
  document.getElementById('speechPlaceholder').style.display = 'none';
  document.getElementById('speechLiveText').textContent = '"' + text + '"';
  collectedTranscripts.push(text);
  analyzeSnippetWithGroq(text);
}

function renderTerms() {
  const container = document.getElementById('termsList');
  document.getElementById('termsCount').textContent = detectedTerms.length + ' terms';

  if (detectedTerms.length === 0) {
    container.innerHTML = `
      <div class="sk-empty-hint" id="termsEmpty">
        <i class="bi bi-book"></i>
        <div>কোনো মেডিকেল শব্দ শনাক্ত হয়নি</div>
        <p>ডাক্তার যখন কোনো রোগ বা টেস্টের নাম বলবেন, এখানে তার সহজ ব্যাখ্যা ফুটে উঠবে।</p>
      </div>
    `;
    return;
  }

  container.innerHTML = detectedTerms.map(t => `
    <div class="sk-term-card">
      <div class="sk-term-word">${t.term}</div>
      <div class="sk-term-bn"><i class="bi bi-lightbulb-fill" style="color:#0f766e;margin-right:0.25rem"></i>${t.explanation_bn}</div>
      <div class="sk-term-en">${t.explanation_en}</div>
    </div>
  `).join('');
}

function renderQuestions() {
  const container = document.getElementById('questionsList');
  document.getElementById('questionsCount').textContent = suggestedQuestions.length + ' questions';

  if (suggestedQuestions.length === 0) {
    container.innerHTML = `
      <div class="sk-empty-hint" id="questionsEmpty">
        <i class="bi bi-chat-quote"></i>
        <div>সাজেশন অপেক্ষায় আছে</div>
        <p>কথোপকথন শুরু হলে এআই প্রাসঙ্গিক প্রশ্ন তৈরি করে দেবে।</p>
      </div>
    `;
    return;
  }

  container.innerHTML = suggestedQuestions.map(q => `
    <div class="sk-question-card">
      <div class="sk-question-text"><i class="bi bi-chat-dots-fill" style="margin-right:0.25rem"></i>${q.question_bn}</div>
      ${q.question_en ? `<div style="font-size:0.8rem;color:#78350f;margin-bottom:0.2rem">"${q.question_en}"</div>` : ''}
      <div class="sk-question-reason"><strong>কেন জিজ্ঞেস করবেন:</strong> ${q.reason}</div>
    </div>
  `).join('');
}

async function generateConsultationSummary() {
  const area = document.getElementById('summaryResultArea');
  const loading = document.getElementById('summaryLoading');
  const body = document.getElementById('summaryBody');

  area.style.display = 'block';
  loading.style.display = 'block';
  body.innerHTML = '';

  const fullText = collectedTranscripts.join("\n") || "Doctor consultation discussed blood pressure, daily medication schedule, lifestyle diet restrictions, and follow-up in 2 weeks.";

  try {
    const res = await fetch('<?= APP_URL ?>/api/sidekick.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'generate_summary',
        transcript: fullText,
        meta: {
          doctor_name: 'Dr. Farhana Rahman (Senior Consultant)',
          patient_name: '<?= htmlspecialchars($patientName) ?> (<?= htmlspecialchars($patientUid) ?>)',
          specialty: 'Internal Medicine'
        }
      })
    });
    const data = await res.json();
    loading.style.display = 'none';

    if (data.success && data.summary_markdown) {
      body.innerHTML = formatMarkdown(data.summary_markdown);
      window.lastSummaryText = data.summary_markdown;
    } else {
      body.innerHTML = '<p class="text-danger">Failed to generate consultation summary.</p>';
    }
  } catch(e) {
    loading.style.display = 'none';
    body.innerHTML = '<p class="text-danger">Network error connecting to Groq AI.</p>';
  }
}

function copySummaryText() {
  if (window.lastSummaryText) {
    navigator.clipboard.writeText(window.lastSummaryText);
    alert("Summary copied to clipboard!");
  }
}

function formatMarkdown(md) {
  let html = md
    .replace(/^# (.*$)/gim, '<h2 style="font-size:1.35rem;color:#0f172a;margin:1rem 0 .5rem">$1</h2>')
    .replace(/^### (.*$)/gim, '<h4 style="font-size:1.05rem;color:#0f766e;margin:1.1rem 0 .35rem">$1</h4>')
    .replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/gim, '<em>$1</em>')
    .replace(/`([^`]+)`/gim, '<code class="mc-mono" style="background:#e2e8f0;padding:2px 5px;border-radius:4px">$1</code>')
    .replace(/\n\n/gim, '<br><br>');
  return html;
}
</script>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
