<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Router;
use Core\Database;
use Core\Env;

// Load environment variables early
Env::load(dirname(__DIR__));

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load global helper functions
require_once __DIR__ . '/../app/helpers/functions.php';

// Set timezone
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Kolkata');

// Initialize Database Connection
Database::getInstance();

// Initialize Router
$router = new Router();

// Load Routes
require_once __DIR__ . '/../app/routes/web.php';

// Dispatch Request
$router->dispatch();