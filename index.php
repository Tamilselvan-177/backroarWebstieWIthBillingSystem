<?php

require_once __DIR__ . '/vendor/autoload.php';

use Core\Router;
use Core\Database;
use Core\Env;

Env::load(__DIR__);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/app/Helpers/functions.php';

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Kolkata');

Database::getInstance();

$router = new Router();

require_once __DIR__ . '/app/routes/web.php';

$router->dispatch();

