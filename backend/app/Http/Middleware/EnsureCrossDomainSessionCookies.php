<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures session cookies for cross-domain SPA deployments (e.g. Vercel frontend
 * talking directly to Render backend) are tagged with SameSite=None, Secure, and
 * Partitioned. This prevents browsers from dropping or blocking session cookies
 * across domains.
 */
class EnsureCrossDomainSessionCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.env') === 'production') {
            config([
                'session.same_site' => 'none',
                'session.secure' => true,
                'session.partitioned' => true,
            ]);
        }

        $response = $next($request);

        if (config('app.env') === 'production') {
            foreach ($response->headers->getCookies() as $cookie) {
                if (strtolower((string) $cookie->getSameSite()) !== 'none') {
                    $response->headers->setCookie(
                        $cookie->withSameSite('none')->withSecure(true)->withPartitioned(true)
                    );
                }
            }
        }

        return $response;
    }
}
