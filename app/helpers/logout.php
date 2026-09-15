<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/middleware/AuthMiddleware.php';
logout(); // This calls session_destroy() and redirects to /
