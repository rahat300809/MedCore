<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/config/chatbot.php';
require_once __DIR__ . '/../app/services/ChatbotService.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$flashSuccess = '';
$flashError   = '';

// Handle Prompt Save / Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_prompt') {
        $prompt = trim($_POST['system_prompt'] ?? '');
        if (!empty($prompt)) {
            if (saveChatbotCustomSystemPrompt($prompt)) {
                AuditService::log('UPDATE_CHATBOT_PROMPT', [
                    'user_id' => $_SESSION['user_id'],
                    'metadata' => ['length' => strlen($prompt)]
                ]);
                $flashSuccess = 'System prompt updated successfully. The AI Chatbot will now use this prompt.';
            } else {
                $flashError = 'Failed to write custom prompt file. Please check folder permissions.';
            }
        } else {
            $flashError = 'System prompt cannot be empty.';
        }
    } elseif ($action === 'reset_default') {
        $customPromptFile = __DIR__ . '/../app/config/chatbot_custom_prompt.txt';
        if (file_exists($customPromptFile)) {
            unlink($customPromptFile);
        }
        AuditService::log('RESET_CHATBOT_PROMPT', [
            'user_id' => $_SESSION['user_id']
        ]);
        $flashSuccess = 'System prompt reset to default MedCore baseline.';
    }
}

$activePrompt = getChatbotActiveSystemPrompt();
$defaultPrompt = getChatbotDefaultSystemPrompt();
$isCustomized = ($activePrompt !== $defaultPrompt);

$csrf = generateCsrfToken();
require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Admin';
$portalRoleBn = 'Central Administration';
$portalAccent = '#1e40af';
$portalUser = $_SESSION['name'] ?? 'System Admin';
$portalMeta = $_SESSION['email'] ?? 'admin@medcore.local';
$portalTitle = 'Groq AI Chatbot Agent Settings';
$portalSubtitle = 'Configure AI prompt, triage rules, and booking execution parameters';
$portalNav = mc_admin_nav('chatbot');
$portalTopAction = '<a href="' . APP_URL . '/" target="_blank" class="mc-btn mc-btn-secondary mc-btn-sm"><i class="bi bi-box-arrow-up-right"></i> View Live Site</a>';
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<style>
  .prompt-box {
    font-family: ui-monospace, 'JetBrains Mono', monospace;
    font-size: 13px;
    line-height: 1.6;
    background: #fff;
    color: var(--ink);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 14px;
    width: 100%;
    min-height: 360px;
    resize: vertical;
  }
  .prompt-box:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(30, 64, 175, .14);
  }
</style>

<?php if (!empty($flashSuccess)): ?>
  <div class="mc-alert mc-alert-ok"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php if (!empty($flashError)): ?>
  <div class="mc-alert mc-alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<section class="mc-stats" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
  <div class="mc-stat">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div class="l">LLM Inference Engine</div>
      <span class="lab-status info">GROQ LPUs</span>
    </div>
    <div class="v" style="font-size:1.15rem;word-break:break-word;"><?= htmlspecialchars(GROQ_MODEL) ?></div>
    <div style="font-size:.78rem;color:#027a48;margin-top:.25rem;"><i class="bi bi-lightning-charge-fill"></i> Ultra-Low Latency (~200ms)</div>
  </div>

  <div class="mc-stat">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div class="l">Prompt Status</div>
      <?php if ($isCustomized): ?>
        <span class="lab-status warn">CUSTOMIZED</span>
      <?php else: ?>
        <span class="lab-status ok">DEFAULT BASELINE</span>
      <?php endif; ?>
    </div>
    <div class="v" style="font-size:1.15rem;"><?= strlen($activePrompt) ?> chars</div>
    <div style="font-size:.78rem;color:var(--muted);margin-top:.25rem;">Auto-injected with live doctors &amp; departments</div>
  </div>

  <div class="mc-stat">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div class="l">Agentic Capabilities</div>
      <span class="lab-status ok">ACTIVE</span>
    </div>
    <div class="v" style="font-size:1.15rem;">4 Action Flows</div>
    <div style="font-size:.78rem;color:var(--muted);margin-top:.25rem;">Symptom Triage · Doctor Suggestion · Booking · App Guide</div>
  </div>
</section>

<section class="mc-panel" style="margin-bottom:1rem;">
  <div class="mc-panel-h">
    <div>
      <h2>System Prompt Editor</h2>
      <div style="font-size:.82rem;color:var(--muted);">Governs the AI's triage rules, tone of voice, medical guardrails, and booking flows.</div>
    </div>
    <form method="POST" onsubmit="return confirm('Are you sure you want to reset the prompt to default?');" style="margin:0;">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="reset_default">
      <button type="submit" class="mc-btn mc-btn-danger mc-btn-sm"><i class="bi bi-arrow-counterclockwise"></i> Reset to Default Baseline</button>
    </form>
  </div>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="save_prompt">

    <textarea name="system_prompt" class="prompt-box" spellcheck="false"><?= htmlspecialchars($activePrompt) ?></textarea>

    <div class="mc-alert mc-alert-info" style="margin:.9rem 0;font-size:.84rem;">
      <div>
        <strong><i class="bi bi-info-circle"></i> Dynamic Context Note:</strong> At runtime, the server automatically appends:
        <ul style="margin:.35rem 0 0 1.2rem;padding:0;">
          <li>Current verified doctors list with rating scores, consultation fees, and hospital affiliations</li>
          <li>Patient authentication status (logged in name, patient UID, or guest state)</li>
          <li>Booking trigger tags for interactive in-chat booking cards</li>
        </ul>
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end;">
      <button type="submit" class="mc-btn mc-btn-primary"><i class="bi bi-floppy-fill"></i> Save &amp; Apply System Prompt</button>
    </div>
  </form>
</section>

<section class="mc-panel">
  <div class="mc-panel-h">
    <div>
      <h2><i class="bi bi-play-circle-fill" style="color:#027a48;"></i> Instant Chatbot Simulator (Test Bench)</h2>
      <div style="font-size:.82rem;color:var(--muted);">Test symptom evaluation and doctor suggestions right from the admin desk before patients use it.</div>
    </div>
  </div>

  <div class="lab-token-form" style="margin-bottom:.75rem;">
    <input type="text" id="sim-input" placeholder="Type a test symptom (e.g., 'আমার ৩ দিন ধরে খুব বুকে ব্যথা এবং শ্বাসকষ্ট হচ্ছে')">
    <button type="button" id="sim-btn" class="mc-btn mc-btn-primary mc-btn-sm"><i class="bi bi-send-fill"></i> Test Agent</button>
  </div>

  <div id="sim-output" style="display:none;background:var(--bg);border:1px solid var(--line);border-radius:12px;padding:1rem;font-size:.9rem;line-height:1.6;"></div>
</section>

<script>
document.getElementById('sim-btn')?.addEventListener('click', async function() {
  const query = document.getElementById('sim-input').value.trim();
  const outBox = document.getElementById('sim-output');
  if (!query) return;

  this.disabled = true;
  this.innerHTML = 'Thinking...';
  outBox.style.display = 'block';
  outBox.innerHTML = '<div style="color:var(--muted);"><em>Contacting Groq LPUs...</em></div>';

  try {
    const res = await fetch('<?= APP_URL ?>/api/chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: query })
    });
    const data = await res.json();
    if (data.success) {
      outBox.innerHTML = '<strong>AI Assistant Response:</strong><br><div style="white-space:pre-wrap;margin-top:8px;"></div>';
      outBox.querySelector('div').textContent = data.reply;
      if (data.action_cards && data.action_cards.length > 0) {
        const card = document.createElement('div');
        card.className = 'mc-alert mc-alert-info';
        card.style.marginTop = '12px';
        card.textContent = 'Generated Action: Doctor ID ' + data.action_cards[0].doctor_id + ' (' + data.action_cards[0].doctor_name + ')';
        outBox.appendChild(card);
      }
    } else {
      outBox.innerHTML = '<span style="color:var(--danger);">Error: ' + String(data.message || '') + '</span>';
    }
  } catch (err) {
    outBox.innerHTML = '<span style="color:var(--danger);">Network Error: ' + String(err.message || '') + '</span>';
  } finally {
    this.disabled = false;
    this.innerHTML = '<i class="bi bi-send-fill"></i> Test Agent';
  }
});
</script>
<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
