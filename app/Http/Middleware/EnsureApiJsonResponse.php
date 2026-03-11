<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiJsonResponse
{
    /**
     * Ensure API routes never return HTML. If the response is HTML, replace with a JSON error.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/*')) {
            return $response;
        }

        // Do not touch file/download responses (would consume stream or alter headers).
        $contentType = $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/octet-stream')
            || str_contains($contentType, 'application/pdf')
            || $response->headers->get('Content-Disposition')) {
            return $response;
        }

        $isHtml = str_contains(strtolower($contentType), 'text/html')
            || (strlen($response->getContent()) > 0 && preg_match('/^\s*<\s*!?\s*DOCTYPE\s+html/i', $response->getContent()));

        if ($isHtml) {
            $status = $response->getStatusCode();
            return response()->json([
                'success' => false,
                'message' => $status >= 500
                    ? 'An error occurred. Please try again later.'
                    : ($status === 404 ? 'Resource or route not found.' : 'An error occurred.'),
            ], $status >= 400 ? $status : 500);
        }

        return $response;
    }
}
