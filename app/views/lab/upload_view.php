<?php
require_once dirname(__DIR__) . '/portal/navs.php';
$portalRole = 'Laboratory';
$portalRoleBn = 'Lab portal';
$portalUser = $_SESSION['lab_name'] ?? 'Lab';
$portalMeta = ($_SESSION['lab_uid'] ?? '') . ' · Upload';
$portalTitle = 'Upload · ' . ($test['test_name'] ?? 'Result');
$portalSubtitle = 'Enter findings and attach files';
$portalNav = mc_lab_nav('upload');
$portalTopAction = '<a class="mc-btn mc-btn-secondary mc-btn-sm" href="' . APP_URL . '/lab/search-token.php?token=' . urlencode($order['lab_token']) . '">Back to order</a>';
$bodyClass = 'lab-desk';
require dirname(__DIR__) . '/layout/portal_shell_start.php';
$csrf = generateCsrfToken();
?>

<?php if ($error): ?><div class="mc-alert mc-alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="mc-alert mc-alert-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="mc-panel">
  <div class="mc-panel-h">
    <div>
      <div class="mc-mono" style="color:var(--accent);font-size:.85rem"><?= htmlspecialchars($order['lab_token']) ?></div>
      <h2 style="margin-top:.2rem;font-size:1.2rem"><?= htmlspecialchars($test['test_name']) ?></h2>
    </div>
  </div>
  <div style="font-size:.9rem;color:var(--muted)">
    Patient: <strong style="color:var(--ink)"><?= htmlspecialchars($order['patient_name']) ?></strong>
    (<?= htmlspecialchars($order['patient_uid']) ?>)
    · Dr. <?= htmlspecialchars(preg_replace('/^Dr\.?\s+/i', '', (string)$order['doctor_name'])) ?>
    <?php if (!empty($test['test_category'])): ?> · <?= htmlspecialchars($test['test_category']) ?><?php endif; ?>
    · <?= htmlspecialchars($test['urgency'] ?? 'ROUTINE') ?>
  </div>
</div>

<form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
  <input type="hidden" name="upload_result" value="1">

  <div class="mc-panel">
    <div class="mc-panel-h"><h2>Result details</h2></div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.55rem;margin-bottom:1rem">
      <?php
      $statuses = [
        'NORMAL' => 'Normal',
        'ABNORMAL' => 'Abnormal',
        'CRITICAL' => 'Critical',
        'PENDING_REVIEW' => 'Pending review',
      ];
      $cur = $existingResult['result_status'] ?? 'PENDING_REVIEW';
      foreach ($statuses as $val => $label):
      ?>
        <label style="display:flex;align-items:center;gap:.55rem;border:1px solid var(--line);border-radius:12px;padding:.7rem .85rem;background:#fff;cursor:pointer">
          <input type="radio" name="result_status" value="<?= $val ?>" <?= $cur === $val ? 'checked' : '' ?>>
          <span style="font-weight:700;font-size:.9rem"><?= $label ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem">
      <label class="mc-field" style="margin-top:0">
        <span>Result value</span>
        <input type="text" name="result_value" placeholder="e.g. 5.2 mmol/L" value="<?= htmlspecialchars($existingResult['result_value'] ?? '') ?>">
      </label>
      <label class="mc-field" style="margin-top:0">
        <span>Reference range</span>
        <input type="text" name="reference_range" placeholder="e.g. 3.9–5.6" value="<?= htmlspecialchars($existingResult['reference_range'] ?? '') ?>">
      </label>
    </div>

    <label class="mc-field">
      <span>Summary / interpretation</span>
      <textarea name="result_summary" rows="4" style="width:100%;border:1px solid var(--line);border-radius:12px;padding:.75rem 1rem;font:inherit;background:#fff"><?= htmlspecialchars($existingResult['result_summary'] ?? '') ?></textarea>
    </label>

    <label class="mc-field">
      <span>Technician name</span>
      <input type="text" name="technician_name" placeholder="Optional" value="<?= htmlspecialchars($existingResult['technician_name'] ?? '') ?>">
    </label>
  </div>

  <div class="mc-panel">
    <div class="mc-panel-h"><h2>Attach files</h2></div>

    <?php if (!empty($existingFiles)): ?>
      <div class="mc-list" style="margin-bottom:1rem">
        <?php foreach ($existingFiles as $ef): ?>
          <div class="mc-list-item" style="background:#fff">
            <div>
              <strong><?= htmlspecialchars($ef['original_name'] ?? basename($ef['file_path'])) ?></strong>
              <?php if (!empty($ef['file_size'])): ?>
                <div style="font-size:.8rem;color:var(--muted)"><?= round($ef['file_size']/1024, 1) ?> KB</div>
              <?php endif; ?>
            </div>
            <a class="mc-btn mc-btn-ghost mc-btn-sm" target="_blank" href="<?= APP_URL ?>/storage/uploads/<?= htmlspecialchars($ef['file_path']) ?>">View</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <label class="mc-field" style="margin-top:0">
      <span>PDF / images</span>
      <input type="file" name="result_files[]" id="fileInput" multiple accept=".pdf,.jpg,.jpeg,.png,.webp"
             style="width:100%;border:1px dashed var(--line);border-radius:12px;padding:1rem;background:#f9fafb">
    </label>
    <div id="fileList" style="margin-top:.65rem;font-size:.86rem;color:var(--muted)"></div>
  </div>

  <button type="submit" class="mc-btn mc-btn-primary mc-btn-lg mc-btn-block">
    <?= $existingResult ? 'Update result' : 'Submit result' ?>
  </button>
</form>

<script>
const input = document.getElementById('fileInput');
const list = document.getElementById('fileList');
if (input && list) {
  input.addEventListener('change', () => {
    list.innerHTML = Array.from(input.files).map(f => {
      const size = f.size > 1048576 ? (f.size/1048576).toFixed(1)+' MB' : Math.round(f.size/1024)+' KB';
      return '<div>' + f.name + ' · ' + size + '</div>';
    }).join('');
  });
}
</script>
<?php require dirname(__DIR__) . '/layout/portal_shell_end.php'; ?>
