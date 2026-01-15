<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register middleware aliases required by the application
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class, // Custom role middleware that checks both Spatie and role field
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
        
        // Enable CORS for API routes (React Native compatibility)
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Ensure all API routes return clean JSON errors, never HTML or full trace
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson() || $request->wantsJson() || $request->header('Accept') === 'application/json') {
                // Handle NotFoundHttpException with clean JSON
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    $routeMessage = $e->getMessage();
                    $route = 'unknown';
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
                
                // Handle other exceptions with clean JSON (no trace)
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'An error occurred.',
                ], $statusCode);
            }
            return null;
        });
    })->create();
