<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Compress image files over a size limit (default 1 MB) so they are stored under the limit.
 * Optional max dimension resizes the image first for faster processing and smaller files (e.g. for mobile uploads).
 * Used for all image uploads: profile, banner, product, category, package, visit photos, etc.
 */
class ImageCompressionService
{
    public const DEFAULT_MAX_BYTES = 1 * 1024 * 1024; // 1 MB target

    /** Product main + gallery images (admin upload). */
    public const PRODUCT_GALLERY_MAX_BYTES = 800 * 1024; // 800 KB

    public const PRODUCT_GALLERY_MAX_DIMENSION = 1920;

    /** Variable product option thumbnails. */
    public const PRODUCT_OPTION_MAX_BYTES = 384 * 1024; // 384 KB

    public const PRODUCT_OPTION_MAX_DIMENSION = 800;

    /** Max size for report/visit photos so uploads and loading are fast. */
    public const VISIT_PHOTO_MAX_BYTES = 512 * 1024; // 512 KB

    /** Max longest side in pixels for report/visit photos. */
    public const VISIT_PHOTO_MAX_DIMENSION = 1280;

    /**
     * Compress image at the given path in place if it exceeds maxBytes.
     * Optionally resize to maxDimension (longest side) first for faster compression and smaller files.
     * Supports JPEG, PNG, GIF, WebP (PHP GD).
     *
     * @param  string  $fullPath  Full filesystem path to the image
     * @param  int  $maxBytes  Max size in bytes (default 1 MB)
     * @param  int|null  $maxDimension  Max length of longest side in pixels (e.g. 1280); null = no resize
     * @return bool  True if file was compressed or was already under limit, false on error
     */
    public static function compressIfNeeded(string $fullPath, int $maxBytes = self::DEFAULT_MAX_BYTES, ?int $maxDimension = null): bool
    {
        if (! file_exists($fullPath) || ! is_file($fullPath)) {
            return false;
        }
        $size = filesize($fullPath);
        if ($size === false || $size <= $maxBytes) {
            if ($maxDimension !== null && $maxDimension > 0) {
                $info = @getimagesize($fullPath);
                if ($info !== false) {
                    $w = (int) $info[0];
                    $h = (int) $info[1];
                    $maxSide = max($w, $h);
                    if ($maxSide > $maxDimension) {
                        $image = self::loadImage($fullPath, (int) $info[2]);
                        if ($image !== null) {
                            $scale = $maxDimension / $maxSide;
                            $newW = (int) round($w * $scale);
                            $newH = (int) round($h * $scale);
                            $result = self::resizeAndSave($image, $fullPath, (int) $info[2], $w, $h, $newW, $newH);
                            if (is_resource($image) || $image instanceof \GdImage) {
                                imagedestroy($image);
                            }
                            return $result;
                        }
                    }
                }
            }
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

        if ($size > $maxBytes * 2 && $maxDimension !== null && $maxDimension > 0) {
            $result = self::fastCompressAndSave($image, $fullPath, $type, $width, $height, $maxBytes, $maxDimension);
            if (is_resource($image) || $image instanceof \GdImage) {
                imagedestroy($image);
            }

            return $result;
        }

        $result = self::compressAndSave($image, $fullPath, $type, $width, $height, $maxBytes, $maxDimension);
        if (is_resource($image) || $image instanceof \GdImage) {
            imagedestroy($image);
        }

        return $result;
    }

    /**
     * Compress image stored in Laravel public disk by relative path.
     *
     * @param  int|null  $maxDimension  Max length of longest side in pixels; null = no resize
     */
    public static function compressIfNeededFromPublicPath(string $relativePath, int $maxBytes = self::DEFAULT_MAX_BYTES, ?int $maxDimension = null): bool
    {
        $fullPath = storage_path('app/public/'.ltrim(str_replace('\\', '/', $relativePath), '/'));

        return self::compressIfNeeded($fullPath, $maxBytes, $maxDimension);
    }

    /**
     * Compress a visit/report photo for fast upload and display. Uses 512 KB max and 1280px max dimension.
     */
    public static function compressVisitPhotoFromPublicPath(string $relativePath): bool
    {
        return self::compressIfNeededFromPublicPath(
            $relativePath,
            self::VISIT_PHOTO_MAX_BYTES,
            self::VISIT_PHOTO_MAX_DIMENSION
        );
    }

    /**
     * Optimize product main/gallery image: resize wide photos + compress (admin/API uploads).
     */
    public static function optimizeProductGalleryFromPublicPath(string $relativePath): bool
    {
        return self::compressIfNeededFromPublicPath(
            $relativePath,
            self::PRODUCT_GALLERY_MAX_BYTES,
            self::PRODUCT_GALLERY_MAX_DIMENSION
        );
    }

    /**
     * Optimize variable product option thumbnail (smaller dimensions + file size).
     */
    public static function optimizeProductOptionFromPublicPath(string $relativePath): bool
    {
        return self::compressIfNeededFromPublicPath(
            $relativePath,
            self::PRODUCT_OPTION_MAX_BYTES,
            self::PRODUCT_OPTION_MAX_DIMENSION
        );
    }

    private static function resizeAndSave($image, string $path, int $type, int $srcW, int $srcH, int $outW, int $outH): bool
    {
        $out = imagecreatetruecolor($outW, $outH);
        if ($out === false) {
            return false;
        }
        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            imagealphablending($out, false);
            imagesavealpha($out, true);
            $transparent = imagecolorallocatealpha($out, 255, 255, 255, 127);
            imagefilledrectangle($out, 0, 0, $outW, $outH, $transparent);
        }
        imagecopyresampled($out, $image, 0, 0, 0, 0, $outW, $outH, $srcW, $srcH);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $quality = 82;
        $saved = self::saveImage($out, $path, $type, $ext, $quality);
        imagedestroy($out);

        return $saved;
    }

    private static function loadImage(string $path, int $type): mixed
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => null,
        };
    }

    /**
     * Single-pass resize + encode for very large uploads (fast; used before response returns).
     */
    private static function fastCompressAndSave($image, string $path, int $type, int $width, int $height, int $maxBytes, int $maxDimension): bool
    {
        $scale = 1.0;
        if (max($width, $height) > $maxDimension) {
            $scale = $maxDimension / (float) max($width, $height);
        }
        if ($scale < 1.0) {
            $outW = max(1, (int) round($width * $scale));
            $outH = max(1, (int) round($height * $scale));
            if (! self::resizeAndSave($image, $path, $type, $width, $height, $outW, $outH)) {
                return false;
            }
            $newSize = filesize($path);
            if ($newSize !== false && $newSize <= $maxBytes) {
                return true;
            }
            $image = self::loadImage($path, $type);
            if ($image === null) {
                return true;
            }
            $info = @getimagesize($path);
            if ($info === false) {
                return false;
            }
            $width = (int) $info[0];
            $height = (int) $info[1];
            $type = (int) $info[2];
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $outW = max(1, (int) round($width * min(1.0, $maxDimension / (float) max($width, $height, 1))));
        $outH = max(1, (int) round($height * ($outW / max($width, 1))));
        $out = imagecreatetruecolor($outW, $outH);
        if ($out === false) {
            return false;
        }
        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            imagealphablending($out, false);
            imagesavealpha($out, true);
        }
        imagecopyresampled($out, $image, 0, 0, 0, 0, $outW, $outH, $width, $height);
        $saved = self::saveImage($out, $path, $type, $ext, 78);
        imagedestroy($out);

        return $saved;
    }

    private static function compressAndSave($image, string $path, int $type, int $width, int $height, int $maxBytes, ?int $maxDimension = null): bool
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $quality = 85;
        $scale = 1.0;
        if ($maxDimension !== null && $maxDimension > 0 && max($width, $height) > $maxDimension) {
            $scale = min($scale, $maxDimension / (float) max($width, $height));
        }
        $attempts = 0;
        $maxAttempts = 12;

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
