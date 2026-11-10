<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureAiAssistantAccess;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global API rate limiting (Phase 1).
        $middleware->throttleApi('60,1');

        // Sanctum SPA/cookie authentication frontend requests.
        $middleware->statefulApi();

        // There is no server-rendered login route in this application: the SPA
        // posts to /api/v1/auth/login and there are no auth-protected web
        // routes. Without this, the `auth` middleware tries to redirect guests
        // to a non-existent `login` route, which raises a RouteNotFoundException
        // and turns an unauthenticated request into a 500. Returning null makes
        // the middleware throw AuthenticationException instead, which the
        // exception handler renders as the standard 401 envelope.
        $middleware->redirectGuestsTo(fn () => null);

        // Custom authorization middleware aliases.
        $middleware->alias([
            'role' => CheckRole::class,
            'permission' => CheckPermission::class,
            'ai.access' => EnsureAiAssistantAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API requests (and any client that explicitly expects JSON) must
        // always receive the standardized JPC Portal error envelope.
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Validation errors -> 422
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                Log::debug('API validation failed', ['errors' => $e->errors()]);

                return ApiResponse::validationError(
                    'Validation failed',
                    $e->errors(),
                );
            }
        });

        // Unauthenticated -> 401
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('Unauthenticated', status: 401);
            }
        });

        // Authorization (Gate) -> 403
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('Forbidden', status: 403);
            }
        });

        // Custom role/permission middleware -> 403
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('Forbidden', status: 403);
            }
        });

        // Missing model -> 404
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('Resource not found', status: 404);
            }
        });

        // Route not found -> 404
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('Not found', status: 404);
            }
        });

        // Invalid signature -> 403
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('Invalid signature', status: 403);
            }
        });

        // Method mismatch -> 405
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('Method not allowed', status: 405);
            }
        });

        // Rate limited -> 429 (Retry-After preserved so clients — and the
        // React reporting hub — can wait exactly as long as the limiter
        // demands instead of hammering the endpoint again).
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('Too many requests', status: 429)
                    ->withHeaders($e->getHeaders());
            }
        });

        // Any other HTTP exception -> mapped status code
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Request failed',
                    status: $e->getStatusCode(),
                );
            }
        });

        // Fallback for any other error -> 500 (details hidden outside local env)
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                Log::error('API exception', [
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                $message = app()->hasDebugModeEnabled()
                    ? $e->getMessage()
                    : 'An unexpected error occurred';

                return ApiResponse::error($message, status: 500);
            }
        });
    })->create();
