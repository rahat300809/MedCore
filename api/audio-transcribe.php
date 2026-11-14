<?php
/**
 * MedCore Audio-to-Text Transcription API
 * Uses Groq Whisper API (whisper-large-v3-turbo) to convert speech audio files into text.
 */
if (file_exists(__DIR__ . '/../app/middleware/AuthMiddleware.php')) {
    require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
    require_once __DIR__ . '/../app/config/chatbot.php';
} else {
    require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
    require_once __DIR__ . '/../../app/config/chatbot.php';
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests supported.']);
    exit;
}

$uploadedFile = $_FILES['audio'] ?? $_FILES['file'] ?? null;
if (!$uploadedFile || empty($uploadedFile['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'No audio file provided in request.']);
    exit;
}

$apiKey = GROQ_API_KEY;
if (empty($apiKey)) {
    echo json_encode(['success' => false, 'message' => 'Groq API Key not configured.']);
    exit;
}

$tmpPath = $uploadedFile['tmp_name'];
$fileName = $uploadedFile['name'] ?: 'audio.webm';
$mimeType = $uploadedFile['type'] ?: 'audio/webm';

$cfile = new CURLFile($tmpPath, $mimeType, $fileName);
$postData = [
    'file'            => $cfile,
    'model'           => 'whisper-large-v3-turbo',
    'response_format' => 'json',
    'temperature'     => '0.0'
];

$ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_TIMEOUT        => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $data = json_decode($response, true);
    $text = trim($data['text'] ?? '');
    echo json_encode([
        'success'    => true,
        'text'       => $text,
        'model'      => 'whisper-large-v3-turbo'
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success'    => false,
        'message'    => 'Whisper transcription failed (' . $httpCode . '): ' . ($curlErr ?: substr($response ?: '', 0, 180))
    ]);
}
