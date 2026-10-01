<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * On-demand resized product images for cart/list thumbs (cached under public disk).
 */
final class MediaThumbCache
{
    /**
     * @return array{path: string, full_path: string}|null
     */
    public static function resolve(string $relativePath, ?int $width): ?array
    {
        $relativePath = ltrim(str_replace(['..', '\\'], ['', '/'], $relativePath), '/');
        if ($relativePath === '' || ! Storage::disk('public')->exists($relativePath)) {
            return null;
        }

        $sourceFull = Storage::disk('public')->path($relativePath);
        if ($width === null || $width < 48) {
            return ['path' => $relativePath, 'full_path' => $sourceFull];
        }

        $width = max(48, min(640, $width));
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return ['path' => $relativePath, 'full_path' => $sourceFull];
        }

        $cacheRel = 'cache/thumbs/w'.$width.'/'.$relativePath;
        $cacheFull = Storage::disk('public')->path($cacheRel);

        if (! is_file($cacheFull) || filemtime($cacheFull) < filemtime($sourceFull)) {
            if (! self::writeThumb($sourceFull, $cacheFull, $width, $extension)) {
                return ['path' => $relativePath, 'full_path' => $sourceFull];
            }
        }

        return ['path' => $cacheRel, 'full_path' => $cacheFull];
    }

    private static function writeThumb(string $sourceFull, string $destFull, int $width, string $extension): bool
    {
        if (! function_exists('imagecreatetruecolor')) {
            return false;
        }

        $dir = dirname($destFull);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return false;
        }

        $info = @getimagesize($sourceFull);
        if ($info === false) {
            return false;
        }

        [$srcW, $srcH] = $info;
        if ($srcW <= 0 || $srcH <= 0) {
            return false;
        }

        if ($srcW <= $width) {
            return @copy($sourceFull, $destFull);
        }

        $destH = (int) max(1, round($srcH * ($width / $srcW)));
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourceFull),
            IMAGETYPE_PNG => @imagecreatefrompng($sourceFull),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourceFull) : false,
            IMAGETYPE_GIF => @imagecreatefromgif($sourceFull),
            default => false,
        };
        if ($src === false) {
            return false;
        }

        $dst = imagecreatetruecolor($width, $destH);
        if ($dst === false) {
            imagedestroy($src);

            return false;
        }

        if (in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $width, $destH, $transparent);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $destH, $srcW, $srcH);

        $ok = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($dst, $destFull, 82),
            'png' => imagepng($dst, $destFull, 6),
            'webp' => function_exists('imagewebp') ? imagewebp($dst, $destFull, 80) : imagejpeg($dst, $destFull, 82),
            'gif' => imagegif($dst, $destFull),
            default => false,
        };

        imagedestroy($src);
        imagedestroy($dst);

        return (bool) $ok;
    }
}
