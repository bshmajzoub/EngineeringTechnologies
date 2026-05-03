<?php

use App\Http\Middleware\CheckActiveUser;
use App\Http\Middleware\CheckRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register named middleware aliases used on route groups
        $middleware->alias([
            'role' => CheckRole::class,
            'active' => CheckActiveUser::class,
        ]);

        // Force all API responses to be JSON (no HTML error pages)
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return consistent JSON for unauthenticated API requests
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please log in.',
                ], 401);
            }
        });

        // Return consistent JSON for authorization failures
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden. You do not have permission to perform this action.',
                ], 403);
            }
        });

        // Return consistent JSON when authorization failures are converted to HTTP exceptions
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden. You do not have permission to perform this action.',
                ], 403);
            }
        });

        // Return consistent JSON for validation failures on API routes
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Return consistent JSON for 404 on API routes
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The requested resource was not found.',
                ], 404);
            }
        });

        // Return JSON 429 with Retry-After header when login is rate-limited.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down and try again later.',
                ], 429)->withHeaders([
                    'Retry-After' => $e->getHeaders()['Retry-After'] ?? 60,
                    'X-RateLimit-Limit' => $e->getHeaders()['X-RateLimit-Limit'] ?? '',
                    'X-RateLimit-Remaining' => $e->getHeaders()['X-RateLimit-Remaining'] ?? 0,
                ]);
            }
        });

        // Catch-all: any remaining HTTP exception on an API route returns JSON.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'An unexpected error occurred.',
                ], $e->getStatusCode());
            }
        });
    })->create();
