<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Compress image files over a size limit (default 5 MB) so they are stored under the limit.
 * Used for all image uploads: profile, banner, product, category, package, visit photos, etc.
 */
class ImageCompressionService
{
    public const DEFAULT_MAX_BYTES = 5 * 1024 * 1024; // 5 MB

    /**
     * Compress image at the given path in place if it exceeds maxBytes.
     * Supports JPEG, PNG, GIF, WebP (PHP GD).
     *
     * @param  string  $fullPath  Full filesystem path to the image
     * @param  int  $maxBytes  Max size in bytes (default 5 MB)
     * @return bool  True if file was compressed or was already under limit, false on error
     */
    public static function compressIfNeeded(string $fullPath, int $maxBytes = self::DEFAULT_MAX_BYTES): bool
    {
        if (! file_exists($fullPath) || ! is_file($fullPath)) {
            return false;
        }
        $size = filesize($fullPath);
        if ($size === false || $size <= $maxBytes) {
            return true;
        }

        $info = @getimagesize($fullPath);
        if ($info === false) {
            return false;
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $type = $info[2]; // IMAGETYPE_JPEG, IMAGETYPE_PNG, etc.

        $image = self::loadImage($fullPath, $type);
        if ($image === null) {
            // SVG or unsupported type: leave as-is
            return true;
        }

        $result = self::compressAndSave($image, $fullPath, $type, $width, $height, $maxBytes);
        if (is_resource($image) || $image instanceof \GdImage) {
            imagedestroy($image);
        }

        return $result;
    }

    /**
     * Compress image stored in Laravel public disk by relative path.
     */
    public static function compressIfNeededFromPublicPath(string $relativePath, int $maxBytes = self::DEFAULT_MAX_BYTES): bool
    {
        $fullPath = storage_path('app/public/'.ltrim(str_replace('\\', '/', $relativePath), '/'));

        return self::compressIfNeeded($fullPath, $maxBytes);
    }

    private static function loadImage(string $path, int $type): \GdImage|resource|null
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => null,
        };
    }

    private static function compressAndSave($image, string $path, int $type, int $width, int $height, int $maxBytes): bool
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $quality = 85;
        $scale = 1.0;
        $attempts = 0;
        $maxAttempts = 25;

        while ($attempts < $maxAttempts) {
            $outW = (int) round($width * $scale);
            $outH = (int) round($height * $scale);
            if ($outW < 1 || $outH < 1) {
                break;
            }

            $out = imagecreatetruecolor($outW, $outH);
            if ($out === false) {
                return false;
            }

            // Preserve transparency for PNG/GIF
            if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
                imagealphablending($out, false);
                imagesavealpha($out, true);
                $transparent = imagecolorallocatealpha($out, 255, 255, 255, 127);
                imagefilledrectangle($out, 0, 0, $outW, $outH, $transparent);
            }

            imagecopyresampled($out, $image, 0, 0, 0, 0, $outW, $outH, $width, $height);

            $saved = self::saveImage($out, $path, $type, $ext, $quality);
            imagedestroy($out);
            if (! $saved) {
                return false;
            }

            $newSize = filesize($path);
            if ($newSize !== false && $newSize <= $maxBytes) {
                return true;
            }

            $quality -= 12;
            if ($quality < 40) {
                $quality = 85;
                $scale *= 0.85;
            }
            $attempts++;
        }

        return true;
    }

    private static function saveImage($image, string $path, int $type, string $ext, int $quality): bool
    {
        $q = match (strtolower($ext)) {
            'jpg', 'jpeg' => min(100, max(10, $quality)),
            'webp' => min(100, max(10, $quality)),
            'png' => (int) round(9 - (($quality / 100) * 9)), // 0-9, 9 = smallest
            default => $quality,
        };

        $result = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, $q),
            IMAGETYPE_PNG => imagepng($image, $path, $q),
            IMAGETYPE_GIF => imagegif($image, $path),
            IMAGETYPE_WEBP => imagewebp($image, $path, $q),
            default => false,
        };

        if ($result === false) {
            Log::warning('ImageCompressionService: failed to save image', ['path' => $path]);
        }

        return $result;
    }
}
