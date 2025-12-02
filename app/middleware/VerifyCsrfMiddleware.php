<?php

namespace App\Middleware;

class VerifyCsrfMiddleware
{
    /**
     * Routes that should be excluded from CSRF verification.
     * Supports exact matches only for now.
     */
    protected array $except = [
        '/payment/razorpay/webhook',
    ];

    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper($method) !== 'POST') {
            return;
        }

        $uri = $this->normalizeUri(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

        if ($this->isExcepted($uri)) {
            return;
        }

        if (\csrf_verify()) {
            return;
        }

        http_response_code(419);
        echo 'Page expired. Please refresh and try again.';
        exit;
    }

    protected function isExcepted(string $uri): bool
    {
        foreach ($this->except as $except) {
            if ($uri === $this->normalizeUri($except)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeUri(string $uri): string
    {
        $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '') ?: '';
        if ($scriptName !== '/' && $scriptName !== '\\' && strpos($uri, $scriptName) === 0) {
            $uri = substr($uri, strlen($scriptName));
        }
        if ($uri === '') {
            return '/';
        }
        return '/' . ltrim($uri, '/');
    }
}

