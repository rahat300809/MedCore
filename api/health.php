<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';

$res = [
    'time' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'env_db_host' => getenv('DB_HOST') ?: null,
    'dir' => __DIR__,
    'api_files' => is_dir(__DIR__) ? @scandir(__DIR__) : null,
    'app_config_files' => is_dir(dirname(__DIR__) . '/app/config') ? @scandir(dirname(__DIR__) . '/app/config') : null,
    'task_files' => is_dir('/var/task') ? @scandir('/var/task') : null,
    'task_user_files' => is_dir('/var/task/user') ? @scandir('/var/task/user') : null,
    'db_status' => null,
    'db_error' => null,
];

try {
    $db = getDB();
    $res['db_status'] = 'OK';
    $res['db_driver'] = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $res['user_count'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (\Throwable $e) {
    $res['db_status'] = 'FAILED';
    $res['db_error'] = $e->getMessage();
}

echo json_encode($res, JSON_PRETTY_PRINT);


