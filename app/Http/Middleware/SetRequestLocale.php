<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active locale for web + API requests.
 *
 * Priority: lang query → locale query → X-Locale header → Accept-Language
 * → authenticated user's preferred_locale → session → configured fallback.
 */
class SetRequestLocale
{
    /** @return list<string> */
    private function allowedLocales(): array
    {
        $locales = config('locales.supported', ['en', 'ar', 'ur']);

        return array_values(array_unique(array_map(
            fn (string $l) => strtolower(trim($l)),
            $locales
        )));
    }

    private function fallbackLocale(): string
    {
        $fb = (string) config('locales.fallback', config('app.fallback_locale', 'en'));

        return $this->isAllowed($fb) ? $this->normalize($fb) : 'en';
    }

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
        foreach (['lang', 'locale'] as $queryKey) {
            $candidate = $request->query($queryKey);
            if (is_string($candidate) && $this->isAllowed($candidate)) {
                return $this->normalize($candidate);
            }
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

        return $this->fallbackLocale();
    }

    private function normalize(string $locale): string
    {
        return strtolower(trim($locale));
    }

    private function isAllowed(string $locale): bool
    {
        return in_array($this->normalize($locale), $this->allowedLocales(), true);
    }
}
