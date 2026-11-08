<?php
/**
 * MedCore Database Configuration
 * Supports environment variables for Vercel / Cloud deployments
 * (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_SSL)
 */
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'medcore');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ];
        
        // Enable SSL if specified in environment (common for cloud DBs like Aiven, TiDB, PlanetScale)
        if (getenv('DB_SSL') === 'true' || getenv('MYSQL_ATTR_SSL_CA')) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Render a friendly error UI if accessed via web browser
            if (php_sapi_name() !== 'cli' && !headers_sent()) {
                http_response_code(500);
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
                        <strong>Error details:</strong> ' . htmlspecialchars($e->getMessage()) . '
                    </div>
                    <h6 class="fw-semibold mb-2">How to resolve for Cloud / Vercel:</h6>
                    <ol class="small text-muted mb-4 ps-3">
                        <li class="mb-1">Create a free cloud MySQL database (e.g. on <strong>Aiven</strong>, <strong>TiDB Cloud</strong>, or <strong>Railway</strong>).</li>
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
            die('Database connection failed: ' . $e->getMessage() . '. Please configure DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS environment variables.');
        }
    }
    return $pdo;
}

