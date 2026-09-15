<?php
require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
logout(); // This calls session_destroy() and redirects to /
