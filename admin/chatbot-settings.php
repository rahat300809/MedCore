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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AI Chatbot Agent Configuration — MedCore Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    :root {
      --admin-primary: #1E40AF;
      --admin-dark: #0F172A;
    }
    .prompt-box {
      font-family: 'JetBrains Mono', monospace;
      font-size: 13px;
      line-height: 1.6;
      background: #FFFFFF;
      color: #0F172A;
      border: 1.5px solid #CBD5E1;
      border-radius: 12px;
      padding: 16px;
      width: 100%;
      min-height: 380px;
      resize: vertical;
      outline: none;
    }
    .prompt-box:focus {
      border-color: #0284C7;
      box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
    }
    .badge-custom {
      background: #FEF3C7;
      color: #B45309;
      border: 1px solid #FCD34D;
      font-size: 11px;
      font-weight: 800;
      padding: 3px 8px;
      border-radius: 6px;
    }
    .badge-default {
      background: #ECFDF5;
      color: #047857;
      border: 1px solid #A7F3D0;
      font-size: 11px;
      font-weight: 800;
      padding: 3px 8px;
      border-radius: 6px;
    }
  </style>
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar" style="background: var(--admin-dark);">
    <div class="sidebar-brand" style="border-color: rgba(255,255,255,0.1);">
      <div class="brand-icon" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);">M</div>
      <div>
        <div class="brand-name" style="color:white;">MedCore</div>
        <div class="brand-sub" style="color: #94A3B8; font-size:11px;">Central Administration</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title" style="color: #64748B;">Central Oversight</div>
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i> System Dashboard
      </a>
      <a href="<?= APP_URL ?>/admin/doctors.php" class="nav-item">
        <i class="bi bi-person-badge-fill"></i> Doctor Verification
      </a>
      <a href="<?= APP_URL ?>/admin/hospitals.php" class="nav-item">
        <i class="bi bi-building-fill-check"></i> Hospital Network
      </a>
      <a href="<?= APP_URL ?>/admin/laboratories.php" class="nav-item">
        <i class="bi bi-flask-fill"></i> Diagnostic Labs
      </a>
      <a href="<?= APP_URL ?>/admin/audit-logs.php" class="nav-item">
        <i class="bi bi-shield-check"></i> Audit Logs
      </a>

      <div class="nav-section-title" style="color: #64748B; margin-top:20px;">AI & Platform Intelligence</div>
      <a href="<?= APP_URL ?>/admin/chatbot-settings.php" class="nav-item active">
        <i class="bi bi-robot"></i> AI Chatbot Settings
      </a>
    </nav>

    <div class="sidebar-footer" style="border-color: rgba(255,255,255,0.1);">
      <div class="user-chip">
        <div class="user-avatar" style="background:#2563EB;">SA</div>
        <div class="user-info">
          <div class="user-name" style="color:white;"><?= htmlspecialchars($_SESSION['name'] ?? 'System Admin') ?></div>
          <div class="user-role" style="color:#94A3B8;">Root Administrator</div>
        </div>
      </div>
      <a href="<?= APP_URL ?>/logout.php" class="logout-btn" title="Sign out" style="color:#EF4444;">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <div class="topbar">
      <div style="display:flex; align-items:center; gap:12px;">
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle Menu">
          <i class="bi bi-list"></i>
        </button>
        <div>
          <h1 class="topbar-title" style="margin:0; font-size:20px; font-weight:800; color:#0F172A;">
            <i class="bi bi-robot text-primary"></i> Groq AI Chatbot Agent Settings
          </h1>
          <div style="font-size:12px; color:#64748B;">Configure AI prompt, triage rules, and booking execution parameters</div>
        </div>
      </div>
      <div class="topbar-actions">
        <a href="<?= APP_URL ?>/" target="_blank" class="btn btn-secondary btn-sm">
          <i class="bi bi-box-arrow-up-right"></i> View Live Site
        </a>
      </div>
    </div>

    <div class="content-area">
      <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success" style="margin-bottom:20px; background:#ECFDF5; border:1px solid #A7F3D0; color:#065F46; padding:12px 16px; border-radius:10px;">
          <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($flashSuccess) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger" style="margin-bottom:20px; background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:12px 16px; border-radius:10px;">
          <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($flashError) ?>
        </div>
      <?php endif; ?>

      <!-- Engine & Engine Status Cards -->
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:16px; margin-bottom:24px;">
        
        <div class="stat-card-admin">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <span class="stat-label">LLM Inference Engine</span>
            <span style="background:#EFF6FF; color:#1D4ED8; font-size:11px; font-weight:800; padding:2px 8px; border-radius:6px;">GROQ LPUs</span>
          </div>
          <div class="stat-num" style="font-size:1.3rem; margin-top:8px;">
            <?= GROQ_MODEL ?>
          </div>
          <div style="font-size:12px; color:#10B981; margin-top:4px;">
            <i class="bi bi-lightning-charge-fill"></i> Ultra-Low Latency (~200ms)
          </div>
        </div>

        <div class="stat-card-admin">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <span class="stat-label">Prompt Status</span>
            <?php if ($isCustomized): ?>
              <span class="badge-custom">CUSTOMIZED</span>
            <?php else: ?>
              <span class="badge-default">DEFAULT BASELINE</span>
            <?php endif; ?>
          </div>
          <div class="stat-num" style="font-size:1.3rem; margin-top:8px;">
            <?= strlen($activePrompt) ?> chars
          </div>
          <div style="font-size:12px; color:#64748B; margin-top:4px;">
            Auto-injected with live doctors & departments
          </div>
        </div>

        <div class="stat-card-admin">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <span class="stat-label">Agentic Capabilities</span>
            <span class="badge-default">ACTIVE</span>
          </div>
          <div class="stat-num" style="font-size:1.3rem; margin-top:8px;">
            4 Action Flows
          </div>
          <div style="font-size:12px; color:#64748B; margin-top:4px;">
            Symptom Triage · Doctor Suggestion · Booking · App Guide
          </div>
        </div>

      </div>

      <!-- Main Prompt Editor Form -->
      <div class="card" style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:16px; padding:24px; margin-bottom:28px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="font-size:18px; font-weight:800; color:#0F172A; margin:0 0 4px;">System Prompt Editor</h2>
            <div style="font-size:13px; color:#64748B;">This instruction set governs the AI's triage rules, tone of voice, medical guardrails, and booking flows.</div>
          </div>
          
          <form method="POST" onsubmit="return confirm('Are you sure you want to reset the prompt to default?');" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="reset_default">
            <button type="submit" class="btn btn-secondary btn-sm" style="color:#DC2626; border-color:#FECACA; background:#FEF2F2;">
              <i class="bi bi-arrow-counterclockwise"></i> Reset to Default Baseline
            </button>
          </form>
        </div>

        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <input type="hidden" name="action" value="save_prompt">

          <div style="margin-bottom:16px;">
            <textarea name="system_prompt" class="prompt-box" spellcheck="false"><?= htmlspecialchars($activePrompt) ?></textarea>
          </div>

          <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:14px; margin-bottom:18px; font-size:12.5px; color:#475569; line-height:1.6;">
            <strong><i class="bi bi-info-circle text-primary"></i> Dynamic Context Note:</strong> At runtime, the server automatically appends real-time data to this prompt:
            <ul style="margin:6px 0 0 18px; padding:0;">
              <li>Current verified doctors list with rating scores, consultation fees, and hospital affiliations</li>
              <li>Patient authentication status (logged in name, patient UID, or guest state)</li>
              <li>Booking trigger tags for interactive in-chat booking cards</li>
            </ul>
          </div>

          <div style="display:flex; justify-content:flex-end; gap:12px;">
            <button type="submit" class="btn btn-primary" style="padding:10px 24px; font-weight:800;">
              <i class="bi bi-floppy-fill"></i> Save & Apply System Prompt
            </button>
          </div>
        </form>
      </div>

      <!-- Live Simulator / Test Bench -->
      <div class="card" style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:16px; padding:24px;">
        <h3 style="font-size:16px; font-weight:800; color:#0F172A; margin:0 0 6px;">
          <i class="bi bi-play-circle-fill text-success"></i> Instant Chatbot Simulator (Test Bench)
        </h3>
        <p style="font-size:13px; color:#64748B; margin:0 0 16px;">Test symptom evaluation and doctor suggestions right from the admin desk before patients use it.</p>

        <div style="display:flex; gap:10px; margin-bottom:14px;">
          <input type="text" id="sim-input" class="form-control" placeholder="Type a test symptom (e.g., 'আমার ৩ দিন ধরে খুব বুকে ব্যথা এবং শ্বাসকষ্ট হচ্ছে')" style="padding:12px; border-radius:10px;">
          <button type="button" id="sim-btn" class="btn btn-primary" style="padding:0 20px; white-space:nowrap; font-weight:700;">
            <i class="bi bi-send-fill"></i> Test Agent
          </button>
        </div>

        <div id="sim-output" style="display:none; background:#F8FAFC; border:1px solid #CBD5E1; border-radius:12px; padding:16px; font-size:14px; line-height:1.6; color:#0F172A;">
        </div>
      </div>

    </div>
  </main>
</div>

<script>
document.getElementById('sim-btn')?.addEventListener('click', async function() {
  const query = document.getElementById('sim-input').value.trim();
  const outBox = document.getElementById('sim-output');
  if (!query) return;

  this.disabled = true;
  this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Thinking...';
  outBox.style.display = 'block';
  outBox.innerHTML = '<div style="color:#64748B;"><em>Contacting Groq LPUs...</em></div>';

  try {
    const res = await fetch('<?= APP_URL ?>/api/chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: query })
    });
    const data = await res.json();
    if (data.success) {
      outBox.innerHTML = `<strong>AI Assistant Response:</strong><br><div style="white-space:pre-wrap; margin-top:8px;">${data.reply}</div>`;
      if (data.action_cards && data.action_cards.length > 0) {
        outBox.innerHTML += `<div style="margin-top:12px; padding:10px; background:#EFF6FF; border:1px solid #BFDBFE; border-radius:8px;"><strong>Generated Action:</strong> Doctor ID ${data.action_cards[0].doctor_id} (${data.action_cards[0].doctor_name})</div>`;
      }
    } else {
      outBox.innerHTML = `<span style="color:#DC2626;">Error: ${data.message}</span>`;
    }
  } catch (err) {
    outBox.innerHTML = `<span style="color:#DC2626;">Network Error: ${err.message}</span>`;
  } finally {
    this.disabled = false;
    this.innerHTML = '<i class="bi bi-send-fill"></i> Test Agent';
  }
});
</script>

</body>
</html>

