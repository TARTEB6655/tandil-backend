<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAdminLocale
{
    /**
     * Set app locale for admin dashboard from session (admin_locale).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('admin_locale')
            ?? session('app_locale')
            ?? ($request->user()?->preferred_locale)
            ?? config('app.locale');
        $allowed = config('locales.supported', ['en', 'ar', 'ur']);
        if (in_array($locale, $allowed, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
