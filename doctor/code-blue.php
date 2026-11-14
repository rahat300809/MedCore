<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/views/portal/navs.php';

initSession();

// Forward to hospital code blue view with doctor context
require __DIR__ . '/../hospital/code-blue.php';
