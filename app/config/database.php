<?php
/**
 * MedCore Database Configuration
 * Supports:
 * 1. Cloud / Remote MySQL via DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 * 2. Automatic Embedded SQLite fallback on Vercel / serverless when no MySQL is reachable
 */

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'medcore');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // 1. Try MySQL first if DB_HOST is explicitly configured (or local XAMPP)
    $hasCustomDbHost = getenv('DB_HOST') && getenv('DB_HOST') !== 'localhost';
    $isVercel = getenv('VERCEL') == '1' || isset($_SERVER['VERCEL']);

    if ($hasCustomDbHost || !$isVercel) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 2,
        ];
        
        if (getenv('DB_SSL') === 'true' || getenv('MYSQL_ATTR_SSL_CA')) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            return $pdo;
        } catch (\Throwable $e) {
            // If explicit remote MySQL was given and failed, we don't fall back silently
            if ($hasCustomDbHost) {
                renderDbError($e->getMessage());
            }
        }
    }

    // 2. Embedded SQLite Fallback (Bundled DB for 1-click Vercel / demo deployments)
    $bundleDb = dirname(__DIR__, 2) . '/database/medcore.sqlite';
    if (file_exists($bundleDb)) {
        // In serverless environments like Vercel Lambda, the deployment directory is read-only.
        // We copy the database file to /tmp/medcore.sqlite so write operations work.
        $tmpDb = sys_get_temp_dir() . '/medcore.sqlite';
        if (!file_exists($tmpDb) || filemtime($bundleDb) > filemtime($tmpDb)) {
            @copy($bundleDb, $tmpDb);
        }
        $sqliteFile = file_exists($tmpDb) ? $tmpDb : $bundleDb;

        try {
            $pdo = new PDO('sqlite:' . $sqliteFile, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // Register MySQL-compatible SQL functions in SQLite
            $pdo->sqliteCreateFunction('CURDATE', fn() => date('Y-m-d'));
            $pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
            $pdo->sqliteCreateFunction('DATE', fn($d) => substr((string)$d, 0, 10));
            $pdo->sqliteCreateFunction('SHA2', fn($val, $bits = 256) => hash('sha256', (string)$val));
            $pdo->sqliteCreateFunction('TIMESTAMPDIFF', function($unit, $t1, $t2) {
                $sec = strtotime($t2) - strtotime($t1);
                $u = strtoupper((string)$unit);
                if ($u === 'SECOND') return $sec;
                if ($u === 'MINUTE') return (int)floor($sec / 60);
                if ($u === 'HOUR')   return (int)floor($sec / 3600);
                if ($u === 'DAY')    return (int)floor($sec / 86400);
                return $sec;
            });

            return $pdo;
        } catch (\Throwable $sqle) {
            // Continue to fallback error UI if SQLite also fails
        }
    }

    // 3. Fallback Setup Guide UI
    renderDbError('Could not connect to MySQL and embedded database is missing.');
    return $pdo;
}

function renderDbError(string $errorMsg): void {
    if (php_sapi_name() !== 'cli' && !headers_sent()) {
        http_response_code(200);
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Configuration Required — MedCore</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger fs-3">
                            <i class="bi bi-database-exclamation"></i>
                        </div>
                        <div>
                            <h4 class="mb-1 text-slate-800 fw-bold">Database Connection Required</h4>
                            <p class="text-muted mb-0 small">MedCore could not connect to the specified MySQL instance.</p>
                        </div>
                    </div>
                    <div class="alert alert-warning border-0 small mb-4">
                        <strong>Error details:</strong> ' . htmlspecialchars($errorMsg) . '
                    </div>
                    <h6 class="fw-semibold mb-2">How to resolve for Cloud / Vercel:</h6>
                    <ol class="small text-muted mb-4 ps-3">
                        <li class="mb-1">Create a free cloud MySQL database (e.g. on <strong>TiDB Cloud</strong>, <strong>Aiven</strong>, or <strong>Railway</strong>).</li>
                        <li class="mb-1">Import <code>database/schema.sql</code> and <code>database/seed.sql</code> into your remote database.</li>
                        <li class="mb-1">In your Vercel Project Settings &rarr; <strong>Environment Variables</strong>, set:
                            <ul class="mt-1 font-monospace text-dark">
                                <li>DB_HOST = [your-cloud-db-host]</li>
                                <li>DB_PORT = [your-cloud-db-port]</li>
                                <li>DB_NAME = [your-db-name]</li>
                                <li>DB_USER = [your-db-user]</li>
                                <li>DB_PASS = [your-db-password]</li>
                            </ul>
                        </li>
                    </ol>
                    <a href="javascript:location.reload()" class="btn btn-primary rounded-pill px-4">
                        <i class="bi bi-arrow-clockwise me-1"></i> Retry Connection
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
        exit;
    }
    die('Database connection failed: ' . $errorMsg);
}
