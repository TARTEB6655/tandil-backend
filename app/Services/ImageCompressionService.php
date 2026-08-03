<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Compress image files over a size limit (default 1 MB) so they are stored under the limit.
 * Optional max dimension resizes the image first for faster processing and smaller files (e.g. for mobile uploads).
 * Used for all image uploads: profile, banner, product, category, package, visit photos, etc.
 */
class ImageCompressionService
{
    public const DEFAULT_MAX_BYTES = 1 * 1024 * 1024; // 1 MB target

    /** Hard cap for mobile registration / profile photos saved to disk. */
    public const MOBILE_UPLOAD_MAX_BYTES = 2 * 1024 * 1024; // 2 MB

    public const MOBILE_UPLOAD_MAX_DIMENSION = 1920;

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

    /** Vendor store logo / profile picture (mobile Edit Profile). */
    public const VENDOR_PROFILE_PICTURE_MAX_BYTES = self::MOBILE_UPLOAD_MAX_BYTES;

    public const VENDOR_PROFILE_PICTURE_MAX_DIMENSION = self::MOBILE_UPLOAD_MAX_DIMENSION;

    /** Max size for maintenance before/after showcase images. */
    public const MAINTENANCE_PHOTO_MAX_BYTES = 512 * 1024; // 512 KB

    public const MAINTENANCE_PHOTO_MAX_DIMENSION = 1280;

    /** Video banner poster (home Featured Video card) — small + sharp for fast loads. */
    public const VIDEO_BANNER_POSTER_MAX_BYTES = 400 * 1024; // 400 KB

    public const VIDEO_BANNER_POSTER_MAX_DIMENSION = 1280;

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

        // Allow decoding large source images (e.g. 12–48 MP camera photos) without OOM.
        // Harmless if the host disables ini_set.
        @ini_set('memory_limit', '512M');
        clearstatcache(true, $fullPath);
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

        // Whenever a max dimension is configured (all upload profiles set one), use the
        // fast single-decode / single-resize / bounded-encode path so even very large
        // images are shrunk to the KB target quickly. The legacy multi-attempt loop is
        // only kept as a fallback for callers that pass no dimension cap.
        if ($maxDimension !== null && $maxDimension > 0) {
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
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($relativePath);

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
     * Compress maintenance showcase photos for fast list loading on mobile.
     */
    public static function compressMaintenancePhotoFromPublicPath(string $relativePath): bool
    {
        return self::compressIfNeededFromPublicPath(
            $relativePath,
            self::MAINTENANCE_PHOTO_MAX_BYTES,
            self::MAINTENANCE_PHOTO_MAX_DIMENSION
        );
    }

    /**
     * Compress video-banner poster images for fast home-screen loads.
     */
    public static function compressVideoBannerPosterFromPublicPath(string $relativePath): bool
    {
        return self::compressIfNeededFromPublicPath(
            $relativePath,
            self::VIDEO_BANNER_POSTER_MAX_BYTES,
            self::VIDEO_BANNER_POSTER_MAX_DIMENSION
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
     * Optimize vendor profile picture / store logo for fast mobile loading.
     * Accepts large uploads; resizes and compresses to under 2 MB.
     */
    public static function optimizeVendorProfilePictureFromPublicPath(string $relativePath): bool
    {
        return self::compressIfNeededFromPublicPath(
            $relativePath,
            self::VENDOR_PROFILE_PICTURE_MAX_BYTES,
            self::VENDOR_PROFILE_PICTURE_MAX_DIMENSION
        );
    }

    /**
     * Optimize client/staff user profile pictures (same targets as vendor avatars).
     */
    public static function optimizeUserProfilePictureFromPublicPath(string $relativePath): bool
    {
        return self::optimizeVendorProfilePictureFromPublicPath($relativePath);
    }

    /**
     * Compress an upload to under $maxBytes (default 2 MB) BEFORE saving to the public disk.
     * Large camera photos (tens of MB) are resized once and encoded as high-quality JPEG.
     *
     * @throws \InvalidArgumentException when the file cannot be processed safely
     */
    public static function storeCompressedPublic(
        UploadedFile $file,
        string $directory,
        int $maxBytes = self::MOBILE_UPLOAD_MAX_BYTES
    ): string {
        @ini_set('memory_limit', '512M');
        @set_time_limit(90);

        $directory = trim(str_replace('\\', '/', $directory), '/');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType() ?: ''));
        $size = (int) $file->getSize();

        if (in_array($ext, ['heic', 'heif'], true) || str_contains($mime, 'heic') || str_contains($mime, 'heif')) {
            throw new \InvalidArgumentException(
                'HEIC/HEIF photos are not supported. Please upload a JPEG or PNG image and try again.'
            );
        }

        // PDFs are not GD-compressible — only accept when already under the storage cap.
        if ($ext === 'pdf' || str_contains($mime, 'pdf')) {
            if ($size > $maxBytes) {
                throw new \InvalidArgumentException(
                    'PDF must be under 2 MB. Please compress the PDF and try again.'
                );
            }

            return $file->store($directory, 'public');
        }

        $sourcePath = $file->getRealPath() ?: $file->getPathname();
        if (! is_string($sourcePath) || $sourcePath === '' || ! is_file($sourcePath)) {
            throw new \InvalidArgumentException('Upload failed. Please select the file again and retry.');
        }

        $diskSize = (int) (@filesize($sourcePath) ?: 0);
        $size = max($size, $diskSize);

        // Already small enough and is a normal raster image — store as-is (fast path).
        $info = @getimagesize($sourcePath);
        if ($info !== false && $size > 0 && $size <= $maxBytes) {
            return $file->store($directory, 'public');
        }

        if ($info === false) {
            // Unknown/non-decodable binary: keep only when already under the storage cap.
            if ($size > 0 && $size <= $maxBytes) {
                return $file->store($directory, 'public');
            }

            // Empty test stubs / unreadable large blobs.
            if ($diskSize === 0 && $size <= $maxBytes) {
                return $file->store($directory, 'public');
            }

            throw new \InvalidArgumentException(
                'This file type cannot be compressed automatically. Please upload a JPEG or PNG under 2 MB.'
            );
        }

        $workPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tandil_'.Str::uuid()->toString().'.jpg';
        if (! @copy($sourcePath, $workPath)) {
            throw new \InvalidArgumentException('Could not read the uploaded image. Please try again.');
        }

        try {
            if (! self::forceCompressJpegUnder($workPath, $maxBytes, self::MOBILE_UPLOAD_MAX_DIMENSION)) {
                throw new \InvalidArgumentException(
                    'Could not compress this image. Please try a different JPEG or PNG photo.'
                );
            }

            clearstatcache(true, $workPath);
            $finalSize = (int) filesize($workPath);
            if ($finalSize <= 0 || $finalSize > $maxBytes) {
                throw new \InvalidArgumentException(
                    'Image is still too large after compression. Please try a clearer, smaller photo.'
                );
            }

            $relative = $directory.'/'.Str::uuid()->toString().'.jpg';
            $stream = fopen($workPath, 'rb');
            if ($stream === false) {
                throw new \InvalidArgumentException('Could not save the compressed image. Please try again.');
            }
            Storage::disk('public')->put($relative, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            return $relative;
        } finally {
            if (is_file($workPath)) {
                @unlink($workPath);
            }
        }
    }

    /**
     * Fast high-quality JPEG compressor: resize longest side, then step quality / size until under maxBytes.
     */
    public static function forceCompressJpegUnder(string $path, int $maxBytes, int $maxDimension = self::MOBILE_UPLOAD_MAX_DIMENSION): bool
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return false;
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $type = (int) $info[2];
        $image = self::loadImage($path, $type);
        if ($image === null) {
            return false;
        }

        try {
            $dimension = max(640, $maxDimension);
            $qualitySteps = [88, 82, 76, 70, 62, 54, 46];

            while ($dimension >= 640) {
                $scale = min(1.0, $dimension / (float) max($width, $height, 1));
                $outW = max(1, (int) round($width * $scale));
                $outH = max(1, (int) round($height * $scale));
                $out = imagecreatetruecolor($outW, $outH);
                if ($out === false) {
                    return false;
                }

                $white = imagecolorallocate($out, 255, 255, 255);
                imagefilledrectangle($out, 0, 0, $outW, $outH, $white);
                imagecopyresampled($out, $image, 0, 0, 0, 0, $outW, $outH, $width, $height);

                foreach ($qualitySteps as $quality) {
                    if (! imagejpeg($out, $path, $quality)) {
                        imagedestroy($out);

                        return false;
                    }
                    clearstatcache(true, $path);
                    $newSize = filesize($path);
                    if ($newSize !== false && $newSize <= $maxBytes) {
                        imagedestroy($out);

                        return true;
                    }
                }

                imagedestroy($out);
                $dimension = (int) round($dimension * 0.75);
            }

            return false;
        } finally {
            if (is_resource($image) || $image instanceof \GdImage) {
                imagedestroy($image);
            }
        }
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
     * Fast path: one decode (done by caller), one resize to the dimension cap, then a
     * small bounded set of quality steps on the already-resized image (no re-decode /
     * re-resize). Keeps large uploads well under the KB target in ~sub-second time.
     */
    private static function fastCompressAndSave($image, string $path, int $type, int $width, int $height, int $maxBytes, int $maxDimension): bool
    {
        $scale = min(1.0, $maxDimension / (float) max($width, $height, 1));
        $outW = max(1, (int) round($width * $scale));
        $outH = max(1, (int) round($height * $scale));

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
        imagecopyresampled($out, $image, 0, 0, 0, 0, $outW, $outH, $width, $height);

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        // Start at high quality; step down only if still above target. Bounded to a few
        // encodes on the small resized image, so this stays fast for any input size.
        $qualitySteps = [88, 80, 70, 60, 50];
        $saved = false;
        foreach ($qualitySteps as $quality) {
            $saved = self::saveImage($out, $path, $type, $ext, $quality);
            if (! $saved) {
                break;
            }
            clearstatcache(true, $path);
            $newSize = filesize($path);
            if ($newSize === false || $newSize <= $maxBytes) {
                imagedestroy($out);

                return true;
            }
        }

        // Still over target — shrink further once and re-encode.
        if ($saved) {
            $shrink = 0.75;
            $w2 = max(1, (int) round($outW * $shrink));
            $h2 = max(1, (int) round($outH * $shrink));
            $smaller = imagecreatetruecolor($w2, $h2);
            if ($smaller !== false) {
                imagecopyresampled($smaller, $out, 0, 0, 0, 0, $w2, $h2, $outW, $outH);
                imagedestroy($out);
                $saved = self::saveImage($smaller, $path, $type, $ext, 54);
                imagedestroy($smaller);

                return $saved;
            }
        }

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

            clearstatcache(true, $path);
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
