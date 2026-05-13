<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Allow logout even when browser/session CSRF token is stale.
        // This prevents "419 Page Expired" when users hit /logout directly.
        $middleware->validateCsrfTokens(except: [
            'logout',
        ]);

        // Register middleware aliases required by the application
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class, // Custom role middleware that checks both Spatie and role field
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'prevent.admin.cache' => \App\Http\Middleware\PreventAdminCache::class,
            'optional.sanctum' => \App\Http\Middleware\OptionalSanctum::class,
            'set.admin.locale' => \App\Http\Middleware\SetAdminLocale::class,
            'set.request.locale' => \App\Http\Middleware\SetRequestLocale::class,
        ]);

        $middleware->web(prepend: [
            \App\Http\Middleware\SetRequestLocale::class,
        ]);
        
        // All routes in routes/api.php use the api middleware group (prefix /api/).
        // Force JSON for every API; ensure no API ever returns HTML or raw error.
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\SetRequestLocale::class,
            \App\Http\Middleware\ForceJsonResponse::class,
        ], append: [
            \App\Http\Middleware\EnsureApiJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Ensure all API routes return clean JSON errors, never HTML or full trace
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson() || $request->wantsJson() || $request->header('Accept') === 'application/json') {
                // ValidationException: return 422 with errors (getResponse() can be null when thrown from validate())
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    $response = $e->getResponse();
                    if ($response !== null) {
                        return $response;
                    }
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                        'errors' => $e->errors(),
                    ], 422);
                }
                // AuthenticationException: return 401 for unauthenticated API requests
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage() ?: 'Unauthenticated.',
                    ], 401);
                }
                // AuthorizationException: return 403
                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage() ?: 'This action is unauthorized.',
                    ], 403);
                }
                // ModelNotFoundException (e.g. findOrFail): return 404
                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Resource not found.',
                    ], 404);
                }
                // NotFoundHttpException with clean JSON
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    $routeMessage = $e->getMessage();
                    if (preg_match('/route\s+([^\s]+)\s+could not be found/i', $routeMessage, $matches)) {
                        $message = "The route {$matches[1]} could not be found.";
                    } elseif (preg_match('/([^\s]+)\s+could not be found/i', $routeMessage, $matches)) {
                        $message = "The route {$matches[1]} could not be found.";
                    } else {
                        $message = 'Endpoint not found. Check the URL and HTTP method (e.g. PUT for update).';
                    }
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                    ], 404);
                }
                // MethodNotAllowedHttpException: return 405
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Method not allowed for this route.',
                    ], 405);
                }
                // HttpException (403, etc.): use its status code
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage() ?: 'An error occurred.',
                    ], $e->getStatusCode());
                }
                // Other exceptions: for API always show actual error message so client knows what to fix
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                $message = $e->getMessage() ?: 'An error occurred.';
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], $statusCode);
            }
            return null;
        });
    })->create();
