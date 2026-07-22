<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Compress video banner uploads for fast API create/update and small home-screen payloads.
 * Uses ffmpeg when available (720p H.264 + AAC, fast preset, high quality CRF).
 * Falls back to keeping the original file if ffmpeg is missing or compression fails.
 */
class VideoCompressionService
{
    /** Target max size after compression (~8 MB). */
    public const TARGET_MAX_BYTES = 8 * 1024 * 1024;

    /** Skip compression when already under this size (still small enough for home). */
    public const SKIP_UNDER_BYTES = 1 * 1024 * 1024;

    /** Max longest side for banner video (mobile home card). */
    public const MAX_HEIGHT = 720;

    /** H.264 quality: lower = better quality / larger file. 23 is visually high quality. */
    public const CRF = 23;

    /** Encoding speed vs compression efficiency. */
    public const PRESET = 'veryfast';

    /**
     * Compress a video stored on the public disk (in place, replaces with .mp4 when needed).
     *
     * @return string|null Relative public-disk path to use (may change extension to .mp4), or null on hard failure
     */
    public static function compressIfNeededFromPublicPath(string $relativePath): ?string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $disk = Storage::disk('public');

        if (! $disk->exists($relativePath)) {
            return $relativePath;
        }

        $fullPath = $disk->path($relativePath);
        clearstatcache(true, $fullPath);
        $size = is_file($fullPath) ? filesize($fullPath) : false;
        if ($size !== false && $size <= self::SKIP_UNDER_BYTES) {
            return $relativePath;
        }

        $ffmpeg = self::resolveFfmpegBinary();
        if ($ffmpeg === null) {
            Log::warning('VideoCompressionService: ffmpeg not found; storing original video', [
                'path' => $relativePath,
                'size' => $size,
            ]);

            return $relativePath;
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $tmpOut = $dir.DIRECTORY_SEPARATOR.'vb_'.uniqid('cmp_', true).'.mp4';

        // Scale longest side down to 720p height max; keep aspect; even dims for H.264.
        // +faststart moves moov atom forward so mobile playback starts immediately.
        $scaleFilter = "scale=-2:'min(720,ih)'";

        $result = Process::timeout(90)->run([
            $ffmpeg,
            '-y',
            '-i', $fullPath,
            '-vf', $scaleFilter,
            '-map', '0:v:0',
            '-map', '0:a:0?',
            '-c:v', 'libx264',
            '-preset', self::PRESET,
            '-crf', (string) self::CRF,
            '-c:a', 'aac',
            '-b:a', '128k',
            '-movflags', '+faststart',
            '-pix_fmt', 'yuv420p',
            $tmpOut,
        ]);

        if (! $result->successful() || ! is_file($tmpOut) || filesize($tmpOut) === 0) {
            Log::warning('VideoCompressionService: ffmpeg compress failed; keeping original', [
                'path' => $relativePath,
                'exit' => $result->exitCode(),
                'error' => $result->errorOutput(),
            ]);
            @unlink($tmpOut);

            return $relativePath;
        }

        clearstatcache(true, $tmpOut);
        $newSize = filesize($tmpOut) ?: 0;
        $oldSize = $size ?: 0;

        // If compressed file is somehow larger, keep the smaller original.
        if ($oldSize > 0 && $newSize >= $oldSize) {
            @unlink($tmpOut);

            return $relativePath;
        }

        $finalRelative = preg_replace('/\.[^.]+$/', '.mp4', $relativePath) ?: ($relativePath.'.mp4');
        $finalFull = $disk->path($finalRelative);
        $finalDir = dirname($finalFull);
        if (! is_dir($finalDir)) {
            @mkdir($finalDir, 0777, true);
        }

        @unlink($fullPath);
        if (! @rename($tmpOut, $finalFull)) {
            @copy($tmpOut, $finalFull);
            @unlink($tmpOut);
        }

        if ($finalRelative !== $relativePath && $disk->exists($relativePath)) {
            $disk->delete($relativePath);
        }

        Log::info('VideoCompressionService: compressed video', [
            'from' => $relativePath,
            'to' => $finalRelative,
            'before_bytes' => $oldSize,
            'after_bytes' => $newSize,
        ]);

        return $finalRelative;
    }

    /**
     * Locate ffmpeg binary: env FFMPEG_PATH, PATH, or common Windows install locations.
     */
    public static function resolveFfmpegBinary(): ?string
    {
        $configured = env('FFMPEG_PATH');
        if (is_string($configured) && $configured !== '' && self::isExecutable($configured)) {
            return $configured;
        }

        $which = Process::timeout(5)->run([
            PHP_OS_FAMILY === 'Windows' ? 'where' : 'which',
            'ffmpeg',
        ]);
        if ($which->successful()) {
            $line = trim(explode("\n", str_replace("\r", '', $which->output()))[0] ?? '');
            if ($line !== '' && self::isExecutable($line)) {
                return $line;
            }
        }

        $candidates = [];
        if (PHP_OS_FAMILY === 'Windows') {
            $localAppData = getenv('LOCALAPPDATA') ?: '';
            $programFiles = getenv('ProgramFiles') ?: 'C:\\Program Files';
            $candidates = array_filter([
                $localAppData !== '' ? $localAppData.'\\Microsoft\\WinGet\\Links\\ffmpeg.exe' : null,
                $programFiles.'\\ffmpeg\\bin\\ffmpeg.exe',
                'C:\\ffmpeg\\bin\\ffmpeg.exe',
            ]);
        } else {
            $candidates = ['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/homebrew/bin/ffmpeg'];
        }

        foreach ($candidates as $candidate) {
            if (self::isExecutable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public static function isAvailable(): bool
    {
        return self::resolveFfmpegBinary() !== null;
    }

    private static function isExecutable(string $path): bool
    {
        return is_file($path) && (PHP_OS_FAMILY === 'Windows' || is_executable($path));
    }
}
