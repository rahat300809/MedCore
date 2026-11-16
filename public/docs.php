<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/views/media.php';
initSession();

$pageTitle = 'Docs — MedCore Pitch, Architecture & System Brief';
$pageDescription = 'Live pitch deck, technical whitepaper, and system dashboard for MedCore — Bangladesh consent-first EHR with Code Blue and Sidekick AI.';
$bodyClass = 'mc-public mc-intl mc-docs-page';
$extraHead = '<link rel="stylesheet" href="' . APP_URL . '/assets/css/docs.css?v=1">';
require __DIR__ . '/../app/views/layout/head.php';
require __DIR__ . '/../app/views/layout/public_header.php';

$live = 'https://medcore-main-2026.vercel.app';
$repo = 'https://github.com/rahat300809/MedCore';
?>

<section class="mc-docs-hero" aria-label="MedCore documentation">
  <div class="mc-docs-hero-bg" aria-hidden="true"></div>
  <div class="mc-wrap mc-docs-hero-inner">
    <p class="mc-docs-kicker">Live /docs · Pitch + whitepaper + system brief</p>
    <p class="mc-brand-hero">Med<span>Core</span></p>
    <h1>One record. Every care. For Bangladesh.</h1>
    <p class="mc-docs-lede">
      Consent-protected national EHR — hospital Code Blue audio scribe, Sidekick patient advocate,
      and doctor AI briefs that keep treatment history from getting lost.
    </p>
    <div class="mc-stage-cta">
      <a class="mc-btn mc-btn-primary mc-btn-lg" href="<?= APP_URL ?>/role-selection.php">Open portals</a>
      <a class="mc-btn mc-btn-ghost-light mc-btn-lg" href="#architecture">Architecture</a>
    </div>
    <div class="mc-docs-meta">
      <a href="<?= htmlspecialchars($live) ?>" target="_blank" rel="noopener">Live demo</a>
      <span aria-hidden="true">·</span>
      <a href="<?= htmlspecialchars($repo) ?>" target="_blank" rel="noopener">GitHub</a>
      <span aria-hidden="true">·</span>
      <a href="<?= APP_URL ?>/emergency.php?uid=PT-000001">ICE demo</a>
    </div>
  </div>
</section>

<nav class="mc-docs-toc" aria-label="Documentation sections">
  <div class="mc-wrap mc-docs-toc-inner">
    <a href="#pitch">Pitch</a>
    <a href="#problem">Problem</a>
    <a href="#architecture">Architecture</a>
    <a href="#ai">AI systems</a>
    <a href="#stack">Stack</a>
    <a href="#security">Security</a>
    <a href="#tokens">Token discipline</a>
    <a href="#demo">Judge demo</a>
  </div>
</nav>

<section class="mc-section" id="pitch">
  <div class="mc-wrap mc-docs-narrow mc-reveal">
    <div class="mc-section-head">
      <h2>Elevator pitch</h2>
      <p>What judges and investors should remember in one breath.</p>
    </div>
    <p class="mc-docs-prose">
      In Bangladesh, most patients still have no single digital health record. History is scattered across hospitals,
      chambers, labs, and paper — so new medicines can clash with old conditions. In a hospital
      <strong>Code Blue</strong>, an 8–15 person team struggles to log drugs, CPR, and shocks in real time.
      Patients leave consultations unsure <em>why</em> each medicine matters.
    </p>
    <p class="mc-docs-prose">
      <strong>MedCore</strong> is a consent-first grid: only registered doctors and hospitals join; access requires
      patient OTP and expires in 30 minutes. AI turns ER speech into a structured ACLS ledger, explains medicines
      to patients, and briefs doctors with short bedside bullets — without unlocking records without consent.
    </p>
  </div>
</section>

<section class="mc-section mc-docs-alt" id="problem">
  <div class="mc-wrap">
    <div class="mc-section-head mc-reveal">
      <h2>Problem → product</h2>
      <p>Five clinical gaps. Five MedCore answers.</p>
    </div>
    <div class="mc-docs-map mc-reveal">
      <article>
        <h3>Fragmented history</h3>
        <p>One lifelong Patient UID with longitudinal EHR across affiliated facilities.</p>
      </article>
      <article>
        <h3>Unsafe EMR access</h3>
        <p>OTP consent + 30-minute session + audit trail. No silent browsing of PHI.</p>
      </article>
      <article>
        <h3>Code Blue chaos</h3>
        <p>Whisper → Groq structured interventions, timers, ACLS-style run sheet.</p>
      </article>
      <article>
        <h3>Consultation amnesia</h3>
        <p>Sidekick explains jargon, suggests questions, saves visit summary.</p>
      </article>
      <article>
        <h3>Rx without “why”</h3>
        <p>Patient portal medicine cards: indication in Bangla + English.</p>
      </article>
      <article>
        <h3>Bedside speed</h3>
        <p>Doctor AI brief from consented SQL context — short bullets, session-cached.</p>
      </article>
    </div>
  </div>
</section>

<section class="mc-section" id="architecture">
  <div class="mc-wrap">
    <div class="mc-section-head mc-reveal">
      <h2>System architecture</h2>
      <p>Role portals around one consented record — AI services on the edge of clinical actions.</p>
    </div>
    <div class="mc-docs-arch mc-reveal" role="img" aria-label="MedCore architecture diagram">
      <div class="mc-docs-arch-row">
        <div class="mc-docs-node">Patient</div>
        <div class="mc-docs-node">Doctor</div>
        <div class="mc-docs-node">Hospital</div>
        <div class="mc-docs-node">Lab</div>
        <div class="mc-docs-node">Admin</div>
      </div>
      <div class="mc-docs-arch-hub">Consent OTP · 30-min session · Audit log · Patient UID</div>
      <div class="mc-docs-arch-row mc-docs-arch-ai">
        <div class="mc-docs-node accent">AudioToText<br><small>Whisper STT</small></div>
        <div class="mc-docs-node accent">CodeBlueService<br><small>ER ledger + ACLS</small></div>
        <div class="mc-docs-node accent">SidekickService<br><small>Advocate + Rx why</small></div>
        <div class="mc-docs-node accent">Doctor brief<br><small>Session-cached</small></div>
      </div>
      <p class="mc-docs-arch-note">Structured SQL → LLM context. No vector store of clinical notes. Human-in-the-loop UI actions.</p>
    </div>

    <div class="mc-docs-dash mc-reveal">
      <div class="mc-docs-stat">
        <strong>5</strong>
        <span>Role portals</span>
      </div>
      <div class="mc-docs-stat">
        <strong>30m</strong>
        <span>Consent window</span>
      </div>
      <div class="mc-docs-stat">
        <strong>4</strong>
        <span>Clinical AI surfaces</span>
      </div>
      <div class="mc-docs-stat">
        <strong>PHP</strong>
        <span>+ Groq + Vercel</span>
      </div>
    </div>
  </div>
</section>

<section class="mc-section mc-docs-alt" id="ai">
  <div class="mc-wrap">
    <div class="mc-section-head mc-reveal">
      <h2>AI systems</h2>
      <p>Custom PHP clinical agents — not LangGraph/CrewAI. Same STT spine, different role prompts.</p>
    </div>
    <div class="mc-docs-ai mc-reveal">
      <article>
        <p class="mc-pro-tag danger">Hospital ER</p>
        <h3>Code Blue ACLS Co-Pilot</h3>
        <ol>
          <li>Room mic → AudioToText (Whisper)</li>
          <li>Groq cleans + sorts interventions (JSON)</li>
          <li>CPR / epi / pulse timers in UI</li>
          <li>ACLS-style run sheet for the chart</li>
        </ol>
        <a class="mc-btn mc-btn-danger mc-btn-sm" href="<?= APP_URL ?>/hospital/login.php?redirect=<?= urlencode('/hospital/code-blue.php') ?>">Hospital Code Blue</a>
      </article>
      <article>
        <p class="mc-pro-tag">Chamber</p>
        <h3>Sidekick advocate</h3>
        <ol>
          <li>Consultation speech → text</li>
          <li>Bangla/English term explain</li>
          <li>Suggested questions + summary</li>
          <li>Medicine “why” on patient portal</li>
        </ol>
        <a class="mc-btn mc-btn-secondary mc-btn-sm" href="<?= APP_URL ?>/patient/login.php">Patient Sidekick</a>
      </article>
      <article>
        <p class="mc-pro-tag">Clinical desk</p>
        <h3>Doctor bedside brief</h3>
        <ol>
          <li>Consented SQL history packed short</li>
          <li>JSON bullet brief (disease / meds / alerts)</li>
          <li>~10 min session cache — no re-bill spam</li>
          <li>Doctor still owns the decision</li>
        </ol>
        <a class="mc-btn mc-btn-secondary mc-btn-sm" href="<?= APP_URL ?>/doctor/select-hospital.php">Doctor portal</a>
      </article>
    </div>
  </div>
</section>

<section class="mc-section" id="stack">
  <div class="mc-wrap">
    <div class="mc-section-head mc-reveal">
      <h2>Technology stack</h2>
      <p>What actually runs in production.</p>
    </div>
    <div class="mc-docs-table-wrap mc-reveal">
      <table class="mc-docs-table">
        <thead>
          <tr><th>Layer</th><th>Choice</th></tr>
        </thead>
        <tbody>
          <tr><td>Backend</td><td>PHP 8+ services (CodeBlue, Sidekick, AudioToText, OTP, Audit)</td></tr>
          <tr><td>Database</td><td>MySQL / SQLite (Vercel-friendly)</td></tr>
          <tr><td>Frontend</td><td>Semantic HTML + competition design system (Manrope / Source Sans 3)</td></tr>
          <tr><td>LLM</td><td>Groq <code>gpt-oss-120b</code> — structured JSON outputs</td></tr>
          <tr><td>Speech</td><td>Groq Whisper <code>whisper-large-v3-turbo</code></td></tr>
          <tr><td>Hosting</td><td>Vercel serverless (<code>vercel-php</code>)</td></tr>
          <tr><td>Security</td><td>Sessions, CSRF, role auth, consent OTP, audit logs</td></tr>
          <tr><td>Build DLC</td><td>PROJECT.md living PRD + Cursor agentic iterative delivery</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="mc-section mc-docs-alt" id="security">
  <div class="mc-wrap mc-docs-narrow mc-reveal">
    <div class="mc-section-head">
      <h2>Security &amp; provenance</h2>
      <p>AI never bypasses consent.</p>
    </div>
    <ul class="mc-docs-list">
      <li>Record unlock only after patient OTP approval.</li>
      <li>Clinical session auto-expires (30 minutes).</li>
      <li>AI context is built from consented structured SQL fields — not a free-floating vector dump of notes.</li>
      <li>Immutable-style audit of access events for governance review.</li>
      <li>Public ICE card exposes emergency-safe fields only (blood group, allergy, contact) via UID.</li>
    </ul>
    <a class="mc-btn mc-btn-secondary" href="<?= APP_URL ?>/security.php">Security page</a>
  </div>
</section>

<section class="mc-section" id="tokens">
  <div class="mc-wrap mc-docs-narrow mc-reveal">
    <div class="mc-section-head">
      <h2>Token discipline</h2>
      <p>Cost and latency controls used in MedCore — honest list for evaluators.</p>
    </div>
    <ul class="mc-docs-list">
      <li><strong>Structured outputs / JSON mode</strong> — every clinical agent returns schema-shaped JSON.</li>
      <li><strong>Sliding-window / context trimming</strong> — only the fields needed for the task (meds, allergies, recent events) enter the prompt.</li>
      <li><strong>Rolling summary memory</strong> — doctor brief cached in session (~10 min) to avoid repeat LLM calls.</li>
      <li><strong>Cheap-model routing</strong> — Whisper for STT; compact text model for parse/brief/explain.</li>
      <li>We do <em>not</em> use Graphify, Gemini/OpenAI/Anthropic prompt caching products, or LLMLingua in this build.</li>
    </ul>
  </div>
</section>

<section class="mc-section mc-docs-alt" id="demo">
  <div class="mc-wrap">
    <div class="mc-section-head mc-reveal">
      <h2>Judge demo access</h2>
      <p>Universal demo OTP / consent code: <code>1234</code> · password <code>Demo123!</code></p>
    </div>
    <div class="mc-docs-table-wrap mc-reveal">
      <table class="mc-docs-table">
        <thead>
          <tr><th>Role</th><th>Entry</th><th>Demo</th></tr>
        </thead>
        <tbody>
          <tr><td>Patient</td><td><code>/patient/login.php</code></td><td><code>rahat@example.com</code></td></tr>
          <tr><td>Doctor</td><td><code>/doctor/select-hospital.php</code></td><td>ABC Hospital → OTP <code>1234</code></td></tr>
          <tr><td>Hospital</td><td><code>/hospital/login.php</code></td><td><code>admin@abc-hospital.com</code></td></tr>
          <tr><td>Lab</td><td><code>/lab/login.php</code></td><td><code>lab@apexdiagnostics.com</code></td></tr>
          <tr><td>ICE</td><td><code>/emergency.php?uid=PT-000001</code></td><td>No login</td></tr>
        </tbody>
      </table>
    </div>
    <div class="mc-stage-cta mc-reveal" style="margin-top:1.5rem">
      <a class="mc-btn mc-btn-primary" href="<?= APP_URL ?>/role-selection.php">Start demo</a>
      <a class="mc-btn mc-btn-secondary" href="<?= htmlspecialchars($repo) ?>" target="_blank" rel="noopener">View source</a>
    </div>
  </div>
</section>

<section class="mc-section">
  <div class="mc-wrap mc-docs-close mc-reveal">
    <p class="mc-brand-hero" style="font-size:clamp(1.6rem,4vw,2.2rem)">Med<span>Core</span></p>
    <p>Care that remembers for Bangladesh — only with the patient’s consent.</p>
  </div>
</section>

<?php require __DIR__ . '/../app/views/layout/public_footer.php'; ?>
