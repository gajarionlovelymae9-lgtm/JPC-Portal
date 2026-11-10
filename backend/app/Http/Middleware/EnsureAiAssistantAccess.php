<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Restricts JPC AIDE access to authorized roles (Admin, Employee) only.
 * HR and Department users must not access AI assistant APIs.
 */
class EnsureAiAssistantAccess
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $allowed = (array) config('ai.assistant.allowed_roles', ['admin', 'employee']);

        if (! $user || ! $user->hasAnyRole($allowed)) {
            throw new AccessDeniedHttpException('JPC AIDE is available only to authorized Admin and Employee users.');
        }

        return $next($request);
    }
}
