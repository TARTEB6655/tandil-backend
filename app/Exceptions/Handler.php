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
            // Handle ValidationException
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors()
                ], 422);
            }

            // Handle AuthenticationException
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            // Handle AuthorizationException
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized. You do not have permission to perform this action.'
                ], 403);
            }

            // Handle ModelNotFoundException
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'status' => false,
                    'message' => 'Resource not found.'
                ], 404);
            }

            // Generic error handling
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            $message = $e->getMessage() ?: 'Server Error';

            // Don't expose internal errors in production
            if (config('app.debug')) {
                $payload = [
                    'status' => false,
                    'message' => $message,
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            } else {
                $payload = [
                    'status' => false,
                    'message' => $status === 500 ? 'An error occurred. Please try again later.' : $message,
                ];
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
