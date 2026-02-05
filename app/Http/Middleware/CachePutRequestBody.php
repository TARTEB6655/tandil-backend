<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For PUT/PATCH with multipart/form-data, read the raw body as early as possible
 * and cache it on the request. PHP does not populate $_FILES for PUT; the body
 * can only be read once from php://input. Running first in the API pipeline
 * ensures we read before any other code consumes the stream.
 */
class CachePutRequestBody
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('PUT') && ! $request->isMethod('PATCH')) {
            return $next($request);
        }
        if (! str_contains($request->header('Content-Type', ''), 'multipart/form-data')) {
            return $next($request);
        }

        $content = $request->getContent();
        if ($content !== '' && $content !== false) {
            $request->attributes->set('_put_multipart_raw', $content);
        }

        return $next($request);
    }
}
