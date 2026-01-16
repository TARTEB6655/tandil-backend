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
        // For API requests, ALWAYS return JSON (never HTML, even in debug mode)
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

        // Handle ValidationException - Use standardized format with success
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            $errors = $e->errors();
            $firstError = collect($errors)->flatten()->first();
            $message = $e->getMessage() ?: ($firstError ?: 'The given data was invalid.');
            
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
            ], 422);
        }

        // Handle AuthenticationException
        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid authentication token.',
            ], 401);
        }

        // Handle AuthorizationException
        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to perform this action.',
            ], 403);
        }

        // Handle ModelNotFoundException
        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        }

        // Handle QueryException (Database errors)
        if ($e instanceof \Illuminate\Database\QueryException) {
            $dbMessage = $isDebug ? $message : 'Database error occurred.';
            return response()->json([
                'success' => false,
                'message' => $dbMessage,
            ], 500);
        }

        // Handle NotFoundHttpException - Always return clean JSON
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            // Extract route from exception message if available
            $routeMessage = $e->getMessage();
            $route = 'unknown';
            
            // Try to extract route path from various message formats
            if (preg_match('/route\s+([^\s]+)\s+could not be found/i', $routeMessage, $matches)) {
                $route = $matches[1];
            } elseif (preg_match('/([^\s]+)\s+could not be found/i', $routeMessage, $matches)) {
                $route = $matches[1];
            }
            
            return response()->json([
                'success' => false,
                'message' => "The route {$route} could not be found.",
            ], 404);
        }

        // Handle MethodNotAllowedHttpException
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Method not allowed for this route.',
            ], 405);
        }

        // Handle HttpException
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            $statusCode = $e->getStatusCode();
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $statusCode);
        }

        // Generic error handling
        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        
        // For API routes, always return clean JSON (no trace in production, minimal in debug)
        $payload = [
            'success' => false,
            'message' => $isDebug ? $message : ($statusCode === 500 ? 'An error occurred. Please try again later.' : $message),
        ];

        // Only add minimal debug info for API routes (never full trace)
        if ($isDebug && $statusCode >= 500) {
            $payload['error'] = $exceptionClass;
        }

        return response()->json($payload, $statusCode);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid authentication token.',
            ], 401);
        }

        return redirect()->guest(route('login'));
    }
}
