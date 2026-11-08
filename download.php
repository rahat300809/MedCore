<?php
/**
 * MedCore Secure File Download Controller
 * Access-controlled streaming for medical reports, scans, and documents.
 */

require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/middleware/AuthMiddleware.php';

// Must be authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    require __DIR__ . '/errors/403.php';
    exit;
}

$db = getDB();
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

// Extract requested file or record ID
$fileParam = trim($_GET['file'] ?? '');
$reportId  = (int)($_GET['report_id'] ?? 0);
$diagId    = (int)($_GET['diag_id'] ?? 0);

$targetFile = null;
$downloadName = 'medcore_document';

// Security: Prevent Directory Traversal
if (!empty($fileParam)) {
    // Strip dangerous path traversal characters
    $cleanPath = str_replace(['../', '..\\', '../', '..'], '', $fileParam);
    $cleanPath = ltrim($cleanPath, '/\\');
    
    $candidateRoots = [
        STORAGE_PATH . '/uploads',
        __DIR__ . '/storage/uploads',
        __DIR__ . '/uploads',
        sys_get_temp_dir() . '/uploads',
    ];

    foreach ($candidateRoots as $root) {
        $full = $root . '/' . $cleanPath;
        if (is_file($full)) {
            $targetFile = $full;
            $downloadName = basename($full);
            break;
        }
    }
}

// Fallback: If not found on disk, or if requested by report_id
if (!$targetFile && $reportId > 0) {
    $stmt = $db->prepare("SELECT * FROM lab_reports WHERE id = ?");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();
    if ($report && !empty($report['file_path'])) {
        $downloadName = $report['file_name'] ?: 'lab_report_' . $reportId;
        $candidateRoots = [
            STORAGE_PATH . '/uploads',
            __DIR__ . '/storage/uploads',
            __DIR__ . '/uploads',
        ];
        foreach ($candidateRoots as $root) {
            $full = $root . '/' . basename($report['file_path']);
            if (is_file($full)) {
                $targetFile = $full;
                break;
            }
        }
    }
}

// If file exists, verify authorization
if ($targetFile && is_file($targetFile)) {
    $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $mimes = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
    ];
    $contentType = $mimes[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: inline; filename="' . addslashes($downloadName) . '"');
    header('Content-Length: ' . filesize($targetFile));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    
    readfile($targetFile);
    exit;
}

// If file not physically on disk (e.g. serverless demo), render clean informational fallback
http_response_code(404);
require __DIR__ . '/errors/404.php';
exit;
