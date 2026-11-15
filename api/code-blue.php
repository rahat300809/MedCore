<?php
/**
 * MedCore Code Blue & ACLS Assistant API Endpoint
 * Handles live speech parsing, intervention ledger extraction, and AHA ACLS report generation.
 */
if (file_exists(__DIR__ . '/../app/middleware/AuthMiddleware.php')) {
    require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
    require_once __DIR__ . '/../app/services/CodeBlueService.php';
} else {
    require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
    require_once __DIR__ . '/../../app/services/CodeBlueService.php';
}

header('Content-Type: application/json; charset=utf-8');

initSession();
if (!isLoggedIn('DOCTOR') && !isLoggedIn('HOSPITAL_ADMIN')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Doctor or hospital login required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests supported.']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: $_POST;
$action = $body['action'] ?? 'parse_speech';

switch ($action) {
    case 'parse_speech':
        $transcript = trim($body['transcript'] ?? '');
        if (empty($transcript)) {
            echo json_encode(['success' => false, 'message' => 'Transcript is empty.']);
            exit;
        }
        $currentLog = $body['current_log'] ?? [];
        $result = CodeBlueService::parseTranscript($transcript, $currentLog);
        echo json_encode(array_merge(['success' => true], $result), JSON_UNESCAPED_UNICODE);
        break;

    case 'generate_report':
        $sessionData = $body['session_data'] ?? [];
        $result = CodeBlueService::generateAclsReport($sessionData);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
        break;
}
