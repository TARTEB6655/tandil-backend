<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\JsonResponse;

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
        // For API requests or when Accept: application/json header is present, always return JSON
        // Also check if request wants JSON or is an API route
        if ($request->is('api/*') || $request->expectsJson() || $request->wantsJson() || $request->header('Accept') === 'application/json') {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions and return JSON responses.
     */
    protected function handleApiException($request, Throwable $e): JsonResponse
    {
        $isDebug = config('app.debug');
        $exceptionClass = get_class($e);
        $file = $e->getFile();
        $line = $e->getLine();
        $message = $e->getMessage() ?: 'An error occurred';

        // Handle ValidationException - Use Laravel's standard format with status
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return response()->json([
                'status' => false,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        }

        // Handle AuthenticationException
        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Handle AuthorizationException
        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. You do not have permission to perform this action.',
            ], 403);
        }

        // Handle ModelNotFoundException
        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'status' => false,
                'message' => 'Resource not found.',
            ], 404);
        }

        // Handle QueryException (Database errors)
        if ($e instanceof \Illuminate\Database\QueryException) {
            $dbMessage = $isDebug ? $message : 'Database error occurred.';
            return response()->json([
                'status' => false,
                'message' => $dbMessage,
            ], 500);
        }

        // Handle NotFoundHttpException
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return response()->json([
                'status' => false,
                'message' => 'Route not found.',
            ], 404);
        }

        // Handle MethodNotAllowedHttpException
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
            return response()->json([
                'status' => false,
                'message' => 'Method not allowed for this route.',
            ], 405);
        }

        // Handle HttpException
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            $statusCode = $e->getStatusCode();
            return response()->json([
                'status' => false,
                'message' => $message,
            ], $statusCode);
        }

        // Generic error handling
        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        
        $payload = [
            'status' => false,
            'message' => $isDebug ? $message : ($statusCode === 500 ? 'An error occurred. Please try again later.' : $message),
        ];

        // Add debug info only in debug mode
        if ($isDebug) {
            $payload['type'] = $exceptionClass;
            $payload['line'] = $line;
            $payload['file'] = $file;
            $payload['trace'] = $e->getTraceAsString();
        }

        return response()->json($payload, $statusCode);
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
