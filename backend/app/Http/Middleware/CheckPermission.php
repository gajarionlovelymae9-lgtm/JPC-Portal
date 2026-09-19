<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * permission:employees.view or permission:reports.approve,reports.review —
 * allows the request when the authenticated user holds at least one of the
 * given permissions.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): mixed
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyPermission($permissions)) {
            throw new AccessDeniedHttpException('Forbidden');
        }

        return $next($request);
    }
}
