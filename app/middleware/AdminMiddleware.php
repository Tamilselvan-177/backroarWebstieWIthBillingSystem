<?php

namespace App\Middleware;

class AdminMiddleware
{
    public function handle()
    {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $_SESSION['errors'] = ['auth' => 'Admin access required'];
            header('Location: /login');
            exit;
        }
    }
}

