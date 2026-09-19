<?php

namespace App\Services\Auth;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Records authentication events (login, failed, logout).
 *
 * This is the foundation for the future Audit Trail module — the complete
 * audit system is intentionally not implemented yet.
 */
final class LoginActivityService
{
    private const EVENT_LOGIN = 'login';

    private const EVENT_FAILED = 'failed';

    private const EVENT_LOGOUT = 'logout';

    /**
     * Record a successful login.
     */
    public function recordLogin(User $user, Request $request): LoginActivity
    {
        return $this->record($user, $user->email, self::EVENT_LOGIN, $request);
    }

    /**
     * Record a failed authentication attempt (unknown credentials or a
     * blocked account status).
     */
    public function recordFailed(string $email, Request $request): LoginActivity
    {
        return $this->record(null, $email, self::EVENT_FAILED, $request);
    }

    /**
     * Mark the most recent open login session for the user as logged out.
     */
    public function recordLogout(User $user): void
    {
        LoginActivity::query()
            ->where('user_id', $user->id)
            ->where('event', self::EVENT_LOGIN)
            ->whereNull('logged_out_at')
            ->latest('id')
            ->first()
            ?->update(['logged_out_at' => now()]);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function record(
        ?User $user,
        string $email,
        string $event,
        Request $request,
        ?array $meta = null,
    ): LoginActivity {
        return LoginActivity::create([
            'user_id' => $user?->id,
            'email' => $email,
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'meta' => $meta,
        ]);
    }
}
