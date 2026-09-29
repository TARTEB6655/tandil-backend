<?php

use App\Support\MediaUrl;

if (! function_exists('media_url')) {
    function media_url(?string $path): ?string
    {
        return MediaUrl::full($path);
    }
}

if (! function_exists('media_thumb')) {
    /**
     * Fast list/card image URL (cached /media/…?w=).
     */
    function media_thumb(?string $path, int $width = 384): ?string
    {
        return MediaUrl::thumb($path, $width) ?? MediaUrl::full($path);
    }
}
