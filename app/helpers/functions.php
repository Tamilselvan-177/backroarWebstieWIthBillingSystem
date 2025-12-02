<?php

/**
 * Generate URL
 */
function url($path = '')
{
    $config = require __DIR__ . '/../config/app.php';
    return rtrim($config['url'], '/') . '/' . ltrim($path, '/');
}

/**
 * Redirect to URL
 */
function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Get old input value
 */
function old($key, $default = '')
{
    return $_SESSION['old'][$key] ?? $default;
}

/**
 * Flash message
 */
function flash($key, $message = null)
{
    if ($message === null) {
        $value = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $value;
    }
    
    $_SESSION['flash'][$key] = $message;
}

/**
 * Generate slug from string
 */
function slugify($text)
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    return empty($text) ? 'n-a' : $text;
}

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function userId()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Check if user is admin
 */
function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Sanitize input
 */
function clean($data)
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Format price
 */
function formatPrice($price)
{
    return '₹' . number_format($price, 2);
}

/**
 * Calculate image folder path
 */
function getImageFolder($productId)
{
    $config = require __DIR__ . '/../config/app.php';
    $batchSize = (int)($config['images']['batch_size'] ?? 1000);
    if ($batchSize <= 0) { $batchSize = 1000; }
    $folderIndex = (int)floor($productId / $batchSize);
    $folder = str_pad($folderIndex, 3, '0', STR_PAD_LEFT);
    return $folder;
}

/**
 * Get image path for product
 */
function getImagePath($productId)
{
    $folder = getImageFolder($productId);
    return "images/{$folder}/{$productId}.webp";
}

/**
 * Get full image URL
 */
function getImageUrl($productId)
{
    return url(getImagePath($productId));
}

/**
 * CSRF token helpers
 */
function csrf_token()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_verify($token = null)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $sessionToken = $_SESSION['_csrf_token'] ?? '';
    $token = $token ?? ($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$token || !$sessionToken) {
        return false;
    }
    return hash_equals($sessionToken, $token);
}