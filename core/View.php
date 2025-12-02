<?php

namespace Core;

use Twig\Loader\FilesystemLoader;
use Twig\Environment;

class View
{
    private static $twig;

    public static function init()
    {
        if (self::$twig === null) {
            $loader = new FilesystemLoader(__DIR__ . '/../app/views');
            self::$twig = new Environment($loader, [
                'cache' => false, // Set to '../storage/cache/views' in production
                'debug' => true,
                'auto_reload' => true
            ]);
            
            // Add global variables
            $config = require __DIR__ . '/../app/config/app.php';
            self::$twig->addGlobal('app_name', $config['name']);
            self::$twig->addGlobal('app_url', $config['url']);
            
            // Add session data
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            self::$twig->addGlobal('session', $_SESSION);
            self::$twig->addGlobal('csrf_token', csrf_token());
        }
    }

    public static function render($template, $data = [])
    {
        self::init();
        echo self::$twig->render($template, $data);
    }
}