<?php

namespace App\Middleware;

class AdminMiddleware
{
    public function handle()
    {
        $role = $_SESSION['role'] ?? null;
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $scriptBase = dirname($_SERVER['SCRIPT_NAME'] ?? '') ?: '';
        if ($scriptBase !== '/' && $scriptBase !== '\\' && strpos($uri, $scriptBase) === 0) {
            $uri = substr($uri, strlen($scriptBase));
        }
        $uri = '/' . ltrim($uri, '/');
        if ($role === 'staff' && strpos($uri, '/admin/pos') === 0) {
            return;
        }
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $_SESSION['errors'] = ['auth' => 'Admin access required'];
            header('Location: /login');
            exit;
        }
    }
}

