<?php
/**
 * MedCore — Vercel Serverless Master Router & Entry Point
 */

$rootDir = dirname(__DIR__);
$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = parse_url($rawUri, PHP_URL_PATH) ?: '/';

// Normalize subfolder prefix if any
$uri = preg_replace('#^/medcore#', '', $uri);
if ($uri === '' || $uri === '/') {
    $uri = '/index.php';
}

// 1. Static Asset Handler (fall-through for css/js/images/fonts)
if (preg_match('#^/assets/(.+)$#', $uri, $matches)) {
    $assetPath = $rootDir . '/public/assets/' . $matches[1];
    if (is_file($assetPath)) {
        $ext = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
        $mimes = [
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'webp'  => 'image/webp',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'json'  => 'application/json',
        ];
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
        }
        header('Cache-Control: public, max-age=86400');
        readfile($assetPath);
        exit;
    }
}

// 2. Direct script execution
$targetScript = null;

// Case A: Root index
if ($uri === '/index.php') {
    $targetScript = $rootDir . '/public/index.php';
}

// Case B: Direct match on filesystem
if (!$targetScript) {
    $candidate = $rootDir . $uri;
    if (is_file($candidate) && pathinfo($candidate, PATHINFO_EXTENSION) === 'php') {
        $targetScript = $candidate;
    } elseif (is_file($candidate . '.php')) {
        $targetScript = $candidate . '.php';
    }
}

// Case C: Look inside public/
if (!$targetScript) {
    $candidate = $rootDir . '/public' . $uri;
    if (is_file($candidate) && pathinfo($candidate, PATHINFO_EXTENSION) === 'php') {
        $targetScript = $candidate;
    } elseif (is_file($candidate . '.php')) {
        $targetScript = $candidate . '.php';
    }
}

// Execute script if found
if ($targetScript && is_file($targetScript)) {
    chdir(dirname($targetScript));
    require $targetScript;
    exit;
}

// 3. Fallback: 404 page
http_response_code(404);
if (is_file($rootDir . '/errors/404.php')) {
    chdir($rootDir . '/errors');
    require $rootDir . '/errors/404.php';
} else {
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 — Page Not Found</h1></body></html>';
}
exit;
