<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Compress video banner uploads for fast home-screen playback.
 * Uses ffmpeg when available (540p H.264 + AAC + faststart).
 * Falls back to keeping the original file if ffmpeg / proc_open / exec are unavailable
 * (common on shared hosting) — never throws, so create/update still succeed.
 */
class VideoCompressionService
{
    /** Target max size after compression (~4 MB) for quick mobile start. */
    public const TARGET_MAX_BYTES = 4 * 1024 * 1024;

    /** Skip full re-encode when already under this size (still remux for faststart). */
    public const SKIP_UNDER_BYTES = 800 * 1024;

    /** Max height for banner video (mobile home card — lower = faster start). */
    public const MAX_HEIGHT = 540;

    /** H.264 quality: lower = better quality / larger file. 28 keeps size small for banners. */
    public const CRF = 28;

    /** Encoding speed vs compression efficiency. */
    public const PRESET = 'veryfast';

    /**
     * Compress a video stored on the public disk (in place, replaces with .mp4 when needed).
     * Always attempts +faststart so players can start without downloading the whole file.
     *
     * @return string Relative public-disk path to use (may change extension to .mp4)
     */
    public static function compressIfNeededFromPublicPath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $disk = Storage::disk('public');

        if (! $disk->exists($relativePath)) {
            return $relativePath;
        }

        $fullPath = $disk->path($relativePath);
        clearstatcache(true, $fullPath);
        $size = is_file($fullPath) ? filesize($fullPath) : false;

        if (! self::canRunExternalProcess()) {
            Log::warning('VideoCompressionService: proc_open/exec disabled; storing original video', [
                'path' => $relativePath,
                'size' => $size,
            ]);

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

        // Small files: still remux with faststart so playback starts immediately.
        if ($size !== false && $size <= self::SKIP_UNDER_BYTES) {
            return self::ensureFastStartFromPublicPath($relativePath, $ffmpeg);
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $tmpOut = $dir.DIRECTORY_SEPARATOR.'vb_'.uniqid('cmp_', true).'.mp4';

        $args = [
            $ffmpeg,
            '-y',
            '-i', $fullPath,
            '-vf', "scale=-2:'min(".self::MAX_HEIGHT.",ih)'",
            '-map', '0:v:0',
            '-map', '0:a:0?',
            '-c:v', 'libx264',
            '-preset', self::PRESET,
            '-crf', (string) self::CRF,
            '-c:a', 'aac',
            '-b:a', '96k',
            '-movflags', '+faststart',
            '-pix_fmt', 'yuv420p',
            $tmpOut,
        ];

        $ran = self::runCommand($args, 90);
        if (! $ran['ok'] || ! is_file($tmpOut) || filesize($tmpOut) === 0) {
            Log::warning('VideoCompressionService: ffmpeg compress failed; trying faststart remux', [
                'path' => $relativePath,
                'exit' => $ran['exit'],
                'error' => $ran['error'],
            ]);
            @unlink($tmpOut);

            return self::ensureFastStartFromPublicPath($relativePath, $ffmpeg);
        }

        clearstatcache(true, $tmpOut);
        $newSize = filesize($tmpOut) ?: 0;
        $oldSize = $size ?: 0;

        if ($oldSize > 0 && $newSize >= $oldSize) {
            @unlink($tmpOut);

            return self::ensureFastStartFromPublicPath($relativePath, $ffmpeg);
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
     * Remux only: move moov atom to the front (+faststart) without re-encoding.
     * Fixes multi-second stalls when players wait for the whole file.
     */
    public static function ensureFastStartFromPublicPath(string $relativePath, ?string $ffmpeg = null): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            return $relativePath;
        }

        if (! self::canRunExternalProcess()) {
            return $relativePath;
        }

        $ffmpeg = $ffmpeg ?? self::resolveFfmpegBinary();
        if ($ffmpeg === null) {
            return $relativePath;
        }

        $fullPath = $disk->path($relativePath);
        $dir = dirname($fullPath);
        $tmpOut = $dir.DIRECTORY_SEPARATOR.'vb_'.uniqid('fst_', true).'.mp4';

        $args = [
            $ffmpeg,
            '-y',
            '-i', $fullPath,
            '-c', 'copy',
            '-movflags', '+faststart',
            $tmpOut,
        ];

        $ran = self::runCommand($args, 30);
        if (! $ran['ok'] || ! is_file($tmpOut) || filesize($tmpOut) === 0) {
            @unlink($tmpOut);

            return $relativePath;
        }

        $finalRelative = preg_replace('/\.[^.]+$/', '.mp4', $relativePath) ?: ($relativePath.'.mp4');
        $finalFull = $disk->path($finalRelative);

        @unlink($fullPath);
        if (! @rename($tmpOut, $finalFull)) {
            @copy($tmpOut, $finalFull);
            @unlink($tmpOut);
        }

        if ($finalRelative !== $relativePath && $disk->exists($relativePath)) {
            $disk->delete($relativePath);
        }

        Log::info('VideoCompressionService: applied faststart', [
            'path' => $finalRelative,
        ]);

        return $finalRelative;
    }

    /**
     * Locate ffmpeg binary without requiring proc_open (check known paths first).
     */
    public static function resolveFfmpegBinary(): ?string
    {
        $configured = env('FFMPEG_PATH');
        if (is_string($configured) && $configured !== '' && self::isExecutable($configured)) {
            return $configured;
        }

        $candidates = PHP_OS_FAMILY === 'Windows'
            ? array_filter([
                (getenv('LOCALAPPDATA') ?: '').'\\Microsoft\\WinGet\\Links\\ffmpeg.exe',
                (getenv('ProgramFiles') ?: 'C:\\Program Files').'\\ffmpeg\\bin\\ffmpeg.exe',
                'C:\\ffmpeg\\bin\\ffmpeg.exe',
            ])
            : ['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/homebrew/bin/ffmpeg'];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && self::isExecutable($candidate)) {
                return $candidate;
            }
        }

        // Last resort: which/where (needs proc_open or exec)
        if (! self::canRunExternalProcess()) {
            return null;
        }

        $finder = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        $ran = self::runCommand([$finder, 'ffmpeg'], 5);
        if ($ran['ok']) {
            $line = trim(explode("\n", str_replace("\r", '', $ran['output']))[0] ?? '');
            if ($line !== '' && self::isExecutable($line)) {
                return $line;
            }
        }

        return null;
    }

    public static function isAvailable(): bool
    {
        return self::canRunExternalProcess() && self::resolveFfmpegBinary() !== null;
    }

    /**
     * Shared hosts often disable proc_open (Laravel Process requires it).
     */
    public static function canRunExternalProcess(): bool
    {
        return self::isFunctionEnabled('proc_open')
            || self::isFunctionEnabled('exec')
            || self::isFunctionEnabled('shell_exec');
    }

    private static function isFunctionEnabled(string $function): bool
    {
        if (! function_exists($function)) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return ! in_array($function, $disabled, true);
    }

    /**
     * @param  list<string>  $args
     * @return array{ok: bool, exit: int|null, output: string, error: string}
     */
    private static function runCommand(array $args, int $timeoutSeconds): array
    {
        if (self::isFunctionEnabled('proc_open')) {
            try {
                $result = Process::timeout($timeoutSeconds)->run($args);

                return [
                    'ok' => $result->successful(),
                    'exit' => $result->exitCode(),
                    'output' => $result->output(),
                    'error' => $result->errorOutput(),
                ];
            } catch (\Throwable $e) {
                Log::warning('VideoCompressionService: Process failed', ['error' => $e->getMessage()]);

                // Fall through to exec/shell_exec
            }
        }

        $command = self::buildShellCommand($args);

        if (self::isFunctionEnabled('exec')) {
            $outputLines = [];
            $exitCode = 1;
            @exec($command.' 2>&1', $outputLines, $exitCode);
            $output = implode("\n", $outputLines);

            return [
                'ok' => $exitCode === 0,
                'exit' => $exitCode,
                'output' => $output,
                'error' => $exitCode === 0 ? '' : $output,
            ];
        }

        if (self::isFunctionEnabled('shell_exec')) {
            $output = (string) @shell_exec($command.' 2>&1');
            // shell_exec has no exit code; treat non-empty ffmpeg error patterns as failure later via file checks
            return [
                'ok' => true,
                'exit' => null,
                'output' => $output,
                'error' => '',
            ];
        }

        return [
            'ok' => false,
            'exit' => null,
            'output' => '',
            'error' => 'No process runner available (proc_open/exec/shell_exec disabled)',
        ];
    }

    /**
     * @param  list<string>  $args
     */
    private static function buildShellCommand(array $args): string
    {
        return implode(' ', array_map(static fn (string $arg) => escapeshellarg($arg), $args));
    }

    private static function isExecutable(string $path): bool
    {
        return is_file($path) && (PHP_OS_FAMILY === 'Windows' || is_executable($path));
    }
}
