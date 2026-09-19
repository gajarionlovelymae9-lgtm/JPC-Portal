<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * role:admin or role:admin,hr — allows the request only when the
 * authenticated user holds at least one of the given roles.
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole($roles)) {
            throw new AccessDeniedHttpException('Forbidden');
        }

        return $next($request);
    }
}
