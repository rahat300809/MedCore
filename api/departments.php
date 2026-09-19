<?php
/**
 * API: Get active departments for a hospital
 * Returns JSON array of {id, name}
 * Public-ish (read-only, no auth required — department names are not sensitive)
 */
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$hospitalId = (int)($_GET['hospital_id'] ?? 0);

if ($hospitalId <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $db   = getDB();
    $stmt = $db->prepare("SELECT id, name FROM departments WHERE hospital_id = ? AND status = 'ACTIVE' ORDER BY name ASC");
    $stmt->execute([$hospitalId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
} catch (Exception $e) {
    echo json_encode([]);
}
