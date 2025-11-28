<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): Response
    {
        // For API requests, always return JSON responses
        if ($request->is('api/*') || $request->expectsJson()) {
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            $message = $e->getMessage() ?: 'Server Error';

            $payload = [
                'status' => false,
                'message' => $message,
            ];

            // include validation errors when available
            if (property_exists($e, 'errors') && is_array($e->errors())) {
                $payload['errors'] = $e->errors();
            }

            return response()->json($payload, $status);
        }

        return parent::render($request, $e);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['status' => false, 'message' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest(route('login'));
    }
}
