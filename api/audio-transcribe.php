<?php
/**
 * MedCore Audio → Text API
 * Whisper STT (Groq) — same approach as Carleslc/AudioToText, cloud Whisper backend.
 * Optional: refine=1 runs Groq cleanup before returning text.
 */
if (file_exists(__DIR__ . '/../app/middleware/AuthMiddleware.php')) {
    require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
    require_once __DIR__ . '/../app/services/AudioToTextService.php';
} else {
    require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
    require_once __DIR__ . '/../../app/services/AudioToTextService.php';
}

header('Content-Type: application/json; charset=utf-8');

initSession();
$allowed = isLoggedIn('DOCTOR') || isLoggedIn('HOSPITAL_ADMIN') || isLoggedIn('PATIENT');
if (!$allowed) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login required for audio transcription.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST supported.']);
    exit;
}

$uploadedFile = $_FILES['audio'] ?? $_FILES['file'] ?? null;
if (!$uploadedFile || empty($uploadedFile['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'No audio file provided.']);
    exit;
}

$mode = trim($_POST['mode'] ?? 'clinical');
$language = trim($_POST['language'] ?? '') ?: null;
$refine = isset($_POST['refine']) ? (bool)$_POST['refine'] : true;

$result = AudioToTextService::transcribeFile(
    $uploadedFile['tmp_name'],
    $uploadedFile['name'] ?? 'audio.webm',
    $uploadedFile['type'] ?? 'audio/webm',
    $language
);

if (!$result['success']) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

$text = $result['text'] ?? '';
$refined = $text;
if ($refine && $text !== '') {
    $refineMode = ($mode === 'code_blue') ? 'code_blue' : 'clinical';
    $refined = AudioToTextService::refineTranscript($text, $refineMode);
}

echo json_encode([
    'success'       => true,
    'text'          => $refined,
    'raw_text'      => $text,
    'model'         => $result['model'] ?? AudioToTextService::WHISPER_MODEL,
    'engine'        => 'whisper+groq',
    'source_pattern'=> 'AudioToText / Whisper',
], JSON_UNESCAPED_UNICODE);
