<?php
/**
 * MedCore Sidekick — Patient Advocate API Endpoint
 * Handles live speech jargon extraction, suggested question generation, and appointment summaries.
 */
if (file_exists(__DIR__ . '/../app/middleware/AuthMiddleware.php')) {
    require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
    require_once __DIR__ . '/../app/services/SidekickService.php';
} else {
    require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
    require_once __DIR__ . '/../../app/services/SidekickService.php';
}

header('Content-Type: application/json; charset=utf-8');

initSession();
if (!isLoggedIn('PATIENT')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Patient login required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests supported.']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: $_POST;
$action = $body['action'] ?? 'analyze_speech';

switch ($action) {
    case 'analyze_speech':
        $speechText = trim($body['speech_text'] ?? $body['transcript'] ?? '');
        if (empty($speechText)) {
            echo json_encode(['success' => false, 'message' => 'Speech text is empty.']);
            exit;
        }
        $context = $body['context'] ?? [];
        $result = SidekickService::analyzeConsultationSpeech($speechText, $context);
        echo json_encode(array_merge(['success' => true], $result), JSON_UNESCAPED_UNICODE);
        break;

    case 'generate_summary':
        $transcript = trim($body['transcript'] ?? '');
        $meta = $body['meta'] ?? [];
        $result = SidekickService::generateSummary($transcript, $meta);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    case 'explain_medicines':
        $meds = $body['medicines'] ?? [];
        if (!is_array($meds)) {
            echo json_encode(['success' => false, 'message' => 'medicines array required.']);
            exit;
        }
        $result = SidekickService::explainMedicines($meds);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
        break;
}
