<?php

// Try to detect base URL from current request (works with php -S localhost:8000 -t public)
$detectedUrl = null;
if (php_sapi_name() !== 'cli' && isset($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    // When using the built-in server with document root pointed at `public`,
    // links should be generated relative to the host (e.g. http://localhost:8000).
    $detectedUrl = $scheme . '://' . $host;
}

$appName = getenv('APP_NAME') ?: 'Mobile Backcase Store';
$appUrl = getenv('APP_URL') ?: ($detectedUrl ?? 'http://localhost/ecommerce');
$timezone = getenv('APP_TIMEZONE') ?: 'Asia/Kolkata';

return [
    'name' => $appName,
    'url' => $appUrl,
    'timezone' => $timezone,
    
    // Image Settings
    'images' => [
        'base_path' => __DIR__ . '/../../public/images',
        'base_url' => '/public/images',
        'batch_size' => 1000,
        'max_width' => 1200,
        'max_size_kb' => 200,
        'quality' => 80
    ],
    
    // Pagination
    'per_page' => 24,
    
    // Session
    'session_lifetime' => 120, // minutes
];
