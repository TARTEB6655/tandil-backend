<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Shared helper for profile picture uploads across dashboards (supervisor, technician, client).
 * Handles PUT + multipart/form-data when PHP does not populate $_FILES.
 */
class ProfilePictureUploadService
{
    /**
     * Parse PUT/PATCH multipart/form-data body, store profile_picture file on public disk.
     * Returns stored path (e.g. "profiles/xxx.jpg") or null.
     */
    public static function storeFromMultipartPut(Request $request, string $fileKey = 'profile_picture'): ?string
    {
        $content = $request->getContent();
        $contentType = (string) $request->header('Content-Type');
        if ($content === '' || ! preg_match('/boundary=(?:["\'])?([^"\'; \n]+)/', $contentType, $m)) {
            return null;
        }
        $boundary = trim($m[1]);
        $parts = array_slice(explode('--' . $boundary, $content), 1, -1);

        foreach ($parts as $part) {
            if (! str_contains($part, "\r\n\r\n")) {
                continue;
            }
            [$rawHeaders, $body] = explode("\r\n\r\n", $part, 2);
            $body = rtrim($body, "\r\n");
            $name = null;
            $filename = null;
            foreach (explode("\r\n", $rawHeaders) as $header) {
                if (stripos($header, 'Content-Disposition:') === 0) {
                    if (preg_match('/name="([^"]+)"/', $header, $nm)) {
                        $name = $nm[1];
                    }
                    if (preg_match('/filename="([^"]*)"/', $header, $fn)) {
                        $filename = $fn[1];
                    }
                    break;
                }
            }
            if ($name === $fileKey && $body !== '') {
                $ext = pathinfo($filename ?? 'image.jpg', PATHINFO_EXTENSION) ?: 'jpg';
                if (! in_array(strtolower($ext), ['jpeg', 'jpg', 'png', 'gif', 'webp'], true)) {
                    $ext = 'jpg';
                }
                $storedName = 'profiles/' . uniqid('img_', true) . '.' . $ext;
                Storage::disk('public')->put($storedName, $body);
                return $storedName;
            }
        }
        return null;
    }

    /**
     * Build full URL for a stored profile picture path (same as User model /media/ serving).
     */
    public static function fullUrl(?string $path): ?string
    {
        if (empty($path) || ! is_string($path)) {
            return null;
        }
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $base = rtrim(request()->getSchemeAndHttpHost() ?: config('app.url', ''), '/');
        return $base ? ($base . '/media/' . $path) : null;
    }

    /**
     * Full URL for profile picture, or default avatar URL when path is null (for APIs that must always return an image URL).
     * When path is null, returns a generated avatar with initial (e.g. ui-avatars.com) so no server file is needed.
     *
     * @param  string|null  $path  Stored profile picture path
     * @param  string  $initial  Letter for default avatar (e.g. first letter of name)
     */
    public static function fullUrlOrDefault(?string $path, string $initial = 'U'): string
    {
        $url = self::fullUrl($path);
        if ($url !== null) {
            return $url;
        }
        $letter = mb_substr(trim($initial), 0, 1) ?: 'U';
        return 'https://ui-avatars.com/api/?name=' . urlencode(mb_strtoupper($letter)) . '&size=128&background=94a3b8&color=fff';
    }
}
