<?php

namespace App\Helpers;

class ImageUploader
{
    public static function uploadProductImages(int $productId, array $files, array $options = []): array
    {
        $app = require dirname(__DIR__) . '/config/app.php';
        $imgCfg = $app['images'] ?? [];
        $quality = (int)($options['quality_webp'] ?? ($imgCfg['quality'] ?? 80));
        $maxW = (int)($options['max_width'] ?? ($imgCfg['max_width'] ?? 1600));
        $maxH = (int)($options['max_height'] ?? ($imgCfg['max_width'] ?? 1600));
        $thumbW = (int)($options['thumb_width'] ?? 400);
        $thumbH = (int)($options['thumb_height'] ?? 400);
        $maxSizeKb = (int)($options['max_size_kb'] ?? ($imgCfg['max_size_kb'] ?? 2048));
        $basePath = $imgCfg['base_path'] ?? (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'images');
        $baseUrl  = trim($imgCfg['base_url'] ?? '/images', '/');

        $results = [];

        $folder = \getImageFolder($productId);
        $baseDir = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $folder;
        $thumbDir = $baseDir . DIRECTORY_SEPARATOR . 'thumbs';

        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }
        if (!is_dir($thumbDir)) {
            @mkdir($thumbDir, 0775, true);
        }

        $count = is_array($files['name'] ?? null) ? count($files['name']) : 0;
        for ($i = 0; $i < $count; $i++) {
            $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpPath = $files['tmp_name'][$i] ?? '';
            $origName = $files['name'][$i] ?? '';
            $type = strtolower((string)($files['type'][$i] ?? ''));
            $ext = pathinfo($origName, PATHINFO_EXTENSION);
            $size = (int)($files['size'][$i] ?? 0);

            if ($size > $maxSizeKb * 1024) {
                continue;
            }
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($type, $allowedTypes, true)) {
                // try a safer detection
                $info = @getimagesize($tmpPath);
                if (!$info || !in_array(image_type_to_mime_type($info[2] ?? 0), $allowedTypes, true)) {
                    continue;
                }
            }
            $imgInfo = @getimagesize($tmpPath);
            if (!$imgInfo) {
                continue;
            }

            $indexLabel = 'main';
            $unique = 'product_' . (int)$productId . '_' . $indexLabel;

            // compute filename by existing files count in folder for product
            $existingPattern = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'product_' . (int)$productId . '_';
            $nextIndex = self::nextIndex($existingPattern);
            if ($nextIndex > 0) {
                $unique = 'product_' . (int)$productId . '_' . $nextIndex;
            }

            $targetPath = $baseDir . DIRECTORY_SEPARATOR . $unique . '.webp';
            $thumbPath = $thumbDir . DIRECTORY_SEPARATOR . $unique . '.webp';

            $ok = self::saveAsWebp($tmpPath, $targetPath, $quality, $maxW, $maxH);
            if ($ok) {
                self::saveAsWebp($targetPath, $thumbPath, $quality, $thumbW, $thumbH, true);
                $results[] = [
                    'image_path' => '/' . $baseUrl . '/' . $folder . '/' . $unique . '.webp',
                    'thumb_path' => '/' . $baseUrl . '/' . $folder . '/thumbs/' . $unique . '.webp'
                ];
            } else {
                $fallback = $baseDir . DIRECTORY_SEPARATOR . $unique . '.' . ($ext ?: 'jpg');
                @move_uploaded_file($tmpPath, $fallback);
                $results[] = [
                    'image_path' => '/' . $baseUrl . '/' . $folder . '/' . $unique . '.' . ($ext ?: 'jpg'),
                    'thumb_path' => null
                ];
            }
        }

        return $results;
    }

    private static function saveAsWebp(string $sourcePath, string $destPath, int $quality, int $maxW, int $maxH, bool $forceResize = false): bool
    {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }

        $src = self::imageCreateFromAny($sourcePath);
        if (!$src) {
            return false;
        }

        $w = imagesx($src);
        $h = imagesy($src);

        $scale = min($maxW / max(1, $w), $maxH / max(1, $h));
        if (!$forceResize) {
            $scale = min(1.0, $scale);
        }

        $newW = max(1, (int)floor($w * $scale));
        $newH = max(1, (int)floor($h * $scale));

        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        $dir = dirname($destPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $ok = imagewebp($dst, $destPath, $quality);
        imagedestroy($src);
        imagedestroy($dst);
        return (bool)$ok;
    }

    private static function imageCreateFromAny(string $path)
    {
        $info = @getimagesize($path);
        if (!$info) {
            return null;
        }
        $type = $info[2] ?? null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($path);
            case IMAGETYPE_WEBP:
                return @imagecreatefromwebp($path);
            case IMAGETYPE_GIF:
                return @imagecreatefromgif($path);
            default:
                return null;
        }
    }

    public static function deletePaths(array $paths): void
    {
        foreach ($paths as $p) {
            if (!$p) continue;
            $abs = self::toAbsolutePath($p);
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
    }

    public static function toAbsolutePath(string $publicPath): string
    {
        $app = require dirname(__DIR__) . '/config/app.php';
        $imgCfg = $app['images'] ?? [];
        $basePath = $imgCfg['base_path'] ?? (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'images');
        $baseUrl  = '/' . trim($imgCfg['base_url'] ?? '/images', '/');
        $publicPath = ltrim($publicPath, '/');
        // convert '/images/...' to absolute using base_path
        if (str_starts_with('/' . $publicPath, $baseUrl)) {
            $rel = substr('/' . $publicPath, strlen($baseUrl));
            return rtrim($basePath, DIRECTORY_SEPARATOR) . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        }
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $publicPath);
    }

    private static function nextIndex(string $existingPatternDirPrefix): int
    {
        // existingPatternDirPrefix: baseDir/product_{id}_
        $dir = dirname($existingPatternDirPrefix);
        if (!is_dir($dir)) return 0;
        $files = @scandir($dir) ?: [];
        $max = -1;
        foreach ($files as $f) {
            if (preg_match('/^' . preg_quote(basename($existingPatternDirPrefix), '/') . '(main|[0-9]+)\.(webp|jpg|jpeg|png)$/i', $f, $m)) {
                $idx = $m[1] === 'main' ? 0 : (int)$m[1];
                $max = max($max, $idx);
            }
        }
        return $max + 1;
    }
}

