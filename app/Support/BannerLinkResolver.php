<?php

namespace App\Support;

use App\Models\Banner;
use Illuminate\Support\Facades\Route;

class BannerLinkResolver
{
    /**
     * Resolve where a dashboard banner should navigate (internal route or external URL).
     */
    public static function resolve(Banner $banner): ?string
    {
        $actionType = strtolower(trim((string) ($banner->action_type ?? 'none')));
        $actionValue = trim((string) ($banner->action_value ?? ''));
        $legacyLink = trim((string) ($banner->link ?? ''));

        if ($actionType === 'none') {
            return null;
        }

        if ($actionType === 'route' && $actionValue !== '') {
            return self::resolveRouteName($actionValue);
        }

        if ($actionType === 'link') {
            $href = self::resolveLinkValue($actionValue !== '' ? $actionValue : $legacyLink);

            return $href;
        }

        if ($legacyLink !== '') {
            return self::resolveLinkValue($legacyLink);
        }

        if ($actionValue !== '') {
            return self::resolveLinkValue($actionValue);
        }

        return null;
    }

    public static function isExternalUrl(?string $href): bool
    {
        if ($href === null || $href === '') {
            return false;
        }

        $host = parse_url($href, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $appHost = parse_url(config('app.url', ''), PHP_URL_HOST);

        return $appHost === null || strcasecmp($host, (string) $appHost) !== 0;
    }

    /**
     * Parse admin "button link" field into DB fields.
     *
     * @return array{action_type: string, action_value: ?string, link: ?string}
     */
    public static function parseAdminButtonLink(?string $input): array
    {
        $input = trim((string) $input);
        if ($input === '') {
            return ['action_type' => 'none', 'action_value' => null, 'link' => null];
        }

        if (self::looksLikeRouteName($input) && Route::has($input)) {
            return ['action_type' => 'route', 'action_value' => $input, 'link' => null];
        }

        $href = self::resolveLinkValue($input);
        if ($href !== null) {
            return ['action_type' => 'link', 'action_value' => $href, 'link' => $href];
        }

        return ['action_type' => 'none', 'action_value' => null, 'link' => null];
    }

    public static function looksLikeRouteName(string $value): bool
    {
        return ! str_contains($value, '://')
            && ! str_starts_with($value, '/')
            && preg_match('/^[a-z][a-z0-9_.-]*$/i', $value)
            && str_contains($value, '.');
    }

    private static function resolveLinkValue(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (self::looksLikeRouteName($value)) {
            return self::resolveRouteName($value);
        }

        if (str_starts_with($value, '/')) {
            return url($value);
        }

        if (! preg_match('#^https?://#i', $value)) {
            if (preg_match('#^[a-z0-9.-]+\.[a-z]{2,}(/.*)?$#i', $value)) {
                $value = 'https://'.$value;
            } else {
                $mapped = self::mapShortcut($value);
                if ($mapped !== null) {
                    return $mapped;
                }

                return null;
            }
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }

    private static function resolveRouteName(string $routeName): ?string
    {
        $routeName = trim($routeName);
        if ($routeName === '') {
            return null;
        }

        if (! Route::has($routeName)) {
            return null;
        }

        try {
            return route($routeName);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function mapShortcut(string $value): ?string
    {
        $key = strtolower($value);

        $shortcuts = [
            'shop' => 'client.shop.index',
            'products' => 'client.shop.index',
            'cart' => 'client.cart.index',
            'services' => 'client.services.index',
            'dashboard' => 'client.dashboard',
        ];

        if (! isset($shortcuts[$key])) {
            return null;
        }

        return self::resolveRouteName($shortcuts[$key]);
    }
}
