<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAdminLocale
{
    protected array $allowed = ['en', 'ar', 'ur'];

    /**
     * Set app locale for admin dashboard from session (admin_locale).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('admin_locale', config('app.locale'));
        if (in_array($locale, $this->allowed, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
