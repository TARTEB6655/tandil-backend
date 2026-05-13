<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetRequestLocale
{
    /** @var list<string> */
    private array $allowed = ['en', 'ar', 'ur'];

    /**
     * Resolve locale from query/header/user/session and apply globally.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);
        if ($locale !== null) {
            app()->setLocale($locale);

            if ($request->hasSession()) {
                $request->session()->put('app_locale', $locale);
                if ($request->user()?->hasRole('admin')) {
                    $request->session()->put('admin_locale', $locale);
                }
            }
        }

        return $next($request);
    }

    private function resolveLocale(Request $request): ?string
    {
        $candidate = $request->query('locale');
        if (is_string($candidate) && $this->isAllowed($candidate)) {
            return $this->normalize($candidate);
        }

        $candidate = $request->header('X-Locale');
        if (is_string($candidate) && $this->isAllowed($candidate)) {
            return $this->normalize($candidate);
        }

        $accept = (string) $request->header('Accept-Language', '');
        if ($accept !== '') {
            $primary = strtolower(trim(explode(',', $accept)[0] ?? ''));
            if ($primary !== '') {
                $base = explode('-', $primary)[0];
                if (is_string($base) && $this->isAllowed($base)) {
                    return $this->normalize($base);
                }
            }
        }

        $userLocale = $request->user()?->preferred_locale;
        if (is_string($userLocale) && $this->isAllowed($userLocale)) {
            return $this->normalize($userLocale);
        }

        if ($request->hasSession()) {
            $sessionLocale = $request->session()->get('app_locale');
            if (is_string($sessionLocale) && $this->isAllowed($sessionLocale)) {
                return $this->normalize($sessionLocale);
            }
            $adminLocale = $request->session()->get('admin_locale');
            if (is_string($adminLocale) && $this->isAllowed($adminLocale)) {
                return $this->normalize($adminLocale);
            }
        }

        $default = (string) config('app.locale', 'en');

        return $this->isAllowed($default) ? $this->normalize($default) : 'en';
    }

    private function normalize(string $locale): string
    {
        return strtolower(trim($locale));
    }

    private function isAllowed(string $locale): bool
    {
        return in_array($this->normalize($locale), $this->allowed, true);
    }
}

