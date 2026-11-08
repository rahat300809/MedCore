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

    $lastError = '';

    // 1. Try MySQL first if DB_HOST is explicitly configured (remote MySQL or local XAMPP)
    $dbHost = getenv('DB_HOST');
    $isVercel = strpos(__DIR__, '/var/task') !== false 
        || getenv('VERCEL') == '1' 
        || isset($_SERVER['VERCEL']) 
        || getenv('VERCEL_REGION');

    // On Vercel, only try MySQL if a real remote host (not localhost) is given
    $shouldTryMySql = (!$isVercel) || ($dbHost && $dbHost !== 'localhost');

    if ($shouldTryMySql) {
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
            $lastError = 'MySQL (' . DB_HOST . '): ' . $e->getMessage();
            // Silently fall through to embedded SQLite so the live site never breaks
        }
    }

    // 2. Embedded SQLite Fallback (Bundled DB for 1-click Vercel / demo deployments)
    $candidatePaths = [
        dirname(__DIR__, 2) . '/api/medcore.sqlite',
        dirname(__DIR__, 2) . '/database/medcore.sqlite',
        dirname(__DIR__) . '/api/medcore.sqlite',
        dirname(__DIR__) . '/database/medcore.sqlite',
        '/var/task/api/medcore.sqlite',
        '/var/task/user/api/medcore.sqlite',
        '/var/task/database/medcore.sqlite',
        '/var/task/user/database/medcore.sqlite',
        __DIR__ . '/../../api/medcore.sqlite',
        __DIR__ . '/../../database/medcore.sqlite',
    ];

    $bundleDb = null;
    foreach ($candidatePaths as $p) {
        if ($p && file_exists($p)) {
            $bundleDb = $p;
            break;
        }
    }

    if ($bundleDb) {
        // In serverless environments like Vercel Lambda, the deployment directory is read-only.
        // We copy the database file to /tmp/medcore.sqlite so write operations work.
        $tmpDb = sys_get_temp_dir() . '/medcore.sqlite';
        if (!file_exists($tmpDb) || filemtime($bundleDb) > filemtime($tmpDb)) {
            @copy($bundleDb, $tmpDb);
        }
        $sqliteFile = file_exists($tmpDb) ? $tmpDb : $bundleDb;

        if (!class_exists('MedCoreSQLitePDO', false)) {
            class MedCoreSQLitePDO extends PDO {
                #[\ReturnTypeWillChange]
                public function prepare($statement, $options = []) {
                    return parent::prepare(self::translateQuery($statement), $options);
                }
                #[\ReturnTypeWillChange]
                public function query(string $statement, ?int $fetchMode = null, ...$fetch_mode_args) {
                    $rewritten = self::translateQuery($statement);
                    return $fetchMode !== null ? parent::query($rewritten, $fetchMode, ...$fetch_mode_args) : parent::query($rewritten);
                }
                #[\ReturnTypeWillChange]
                public function exec(string $statement): int|false {
                    return parent::exec(self::translateQuery($statement));
                }
                public static function translateQuery(string $sql): string {
                    // In SQLite, DISTINCT aggregates cannot have a second separator argument
                    $sql = preg_replace('/GROUP_CONCAT\(\s*DISTINCT\s+(.*?)\s+SEPARATOR\s+([\'"].*?[\'"])\s*\)/is', 'GROUP_CONCAT(DISTINCT $1)', $sql);
                    $sql = preg_replace('/GROUP_CONCAT\(\s*(.*?)\s+SEPARATOR\s+([\'"].*?[\'"])\s*\)/is', 'GROUP_CONCAT($1, $2)', $sql);
                    $sql = preg_replace('/DATE_ADD\(\s*NOW\(\)\s*,\s*INTERVAL\s+(\d+)\s+MINUTE\s*\)/i', "datetime('now', '+$1 minutes')", $sql);
                    $sql = preg_replace('/DATE_ADD\(\s*NOW\(\)\s*,\s*INTERVAL\s+(\d+)\s+DAY\s*\)/i', "datetime('now', '+$1 days')", $sql);
                    $sql = preg_replace('/DATE_ADD\(\s*NOW\(\)\s*,\s*INTERVAL\s+(\d+)\s+HOUR\s*\)/i', "datetime('now', '+$1 hours')", $sql);
                    $sql = preg_replace('/TIMESTAMPDIFF\(\s*SECOND\s*,\s*NOW\(\)\s*,\s*([a-zA-Z0-9_\.]+)\s*\)/i', "(strftime('%s', $1) - strftime('%s', 'now'))", $sql);
                    return $sql;
                }
            }
        }

        try {
            $pdo = new MedCoreSQLitePDO('sqlite:' . $sqliteFile, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // Register MySQL-compatible SQL functions in SQLite safely without PHP 8.5 deprecation warnings
            $reg = function($name, $fn) use ($pdo) {
                if (method_exists($pdo, 'createFunction')) {
                    $pdo->createFunction($name, $fn);
                } elseif (method_exists($pdo, 'sqliteCreateFunction')) {
                    @$pdo->sqliteCreateFunction($name, $fn);
                }
            };

            $reg('CURDATE', fn() => date('Y-m-d'));
            $reg('NOW', fn() => date('Y-m-d H:i:s'));
            $reg('DATE', fn($d) => substr((string)$d, 0, 10));
            $reg('CONCAT', fn(...$args) => implode('', $args));
            $reg('SHA2', fn($val, $bits = 256) => hash('sha256', (string)$val));
            $reg('TIMESTAMPDIFF', function($unit, $t1, $t2) {
                $sec = strtotime((string)$t2) - strtotime((string)$t1);
                $u = strtoupper((string)$unit);
                if ($u === 'SECOND') return $sec;
                if ($u === 'MINUTE') return (int)floor($sec / 60);
                if ($u === 'HOUR')   return (int)floor($sec / 3600);
                if ($u === 'DAY')    return (int)floor($sec / 86400);
                return $sec;
            });

            return $pdo;
        } catch (\Throwable $sqle) {
            $lastError .= ' | SQLite: ' . $sqle->getMessage();
        }
    }

    // 3. Fallback Setup Guide UI (only if BOTH MySQL and SQLite fail)
    renderDbError($lastError ?: 'Database connection could not be established.');
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
