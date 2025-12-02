<?php

namespace Core;

class Env
{
    public static function load(string $basePath): void
    {
        $envFile = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
        if (!is_readable($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = self::sanitizeValue($value);

            if ($name === '') {
                continue;
            }

            if (getenv($name) !== false) {
                continue;
            }

            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    private static function sanitizeValue(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $quoteChars = ['"', '\''];
        if (in_array($value[0], $quoteChars, true) && str_ends_with($value, $value[0])) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }
}

