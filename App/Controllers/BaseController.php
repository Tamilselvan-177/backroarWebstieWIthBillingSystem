<?php

namespace App\Controllers;

use Core\View;

class BaseController
{
    protected function view($template, $data = [])
    {
        View::render($template, $data);
    }

    protected function redirect($url)
    {
        header("Location: " . $url);
        exit;
    }

    protected function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}