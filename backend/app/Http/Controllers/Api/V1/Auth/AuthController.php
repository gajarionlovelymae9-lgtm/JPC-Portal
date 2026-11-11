<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserProfileResource;
use App\Models\User;
use App\Services\ActivityLogs\ActivityLogService;
use App\Services\Auth\LoginActivityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginActivityService $activity,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * POST /api/v1/auth/login
     *
     * Authenticates the user, starts the web session (Sanctum SPA) and returns
     * the authenticated user's profile. The selected role must match the
     * user's actual role in the database. Failures return a generic message so
     * account existence, status, or password correctness is never revealed.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password', 'role');
        $email = strtolower($credentials['email']);
        $selectedRole = Role::normalizeForLogin($credentials['role'] ?? null);

        $user = User::query()->with('role')->where('email', $email)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->activity->recordFailed($email, $request);

            return ApiResponse::error(
                'These credentials do not match our records.',
                status: JsonResponse::HTTP_UNAUTHORIZED,
            );
        }

        if (! $user->canAuthenticate()) {
            $this->activity->recordFailed($email, $request);

            return ApiResponse::error(
                'These credentials do not match our records.',
                status: JsonResponse::HTTP_UNAUTHORIZED,
            );
        }

        $actualRole = $user->roleName();
        if ($selectedRole === null || $actualRole !== $selectedRole) {
            $this->activity->recordFailed($email, $request);

            return ApiResponse::error(
                'The selected role does not match this account.',
                status: JsonResponse::HTTP_UNAUTHORIZED,
            );
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
        $this->activity->recordLogin($user, $request);
        $this->activityLog->login($user);

        return ApiResponse::success('Login successful', new UserProfileResource($user));
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Invalidates the current authenticated session.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $this->activity->recordLogout($user);
            $this->activityLog->logout($user);
        }

        Auth::guard('web')->logout();
        $session = $request->session();
        $session->invalidate();
        $session->regenerateToken();

        return ApiResponse::success('Logout successful');
    }

    /**
     * GET /api/v1/auth/me
     *
     * Returns the currently authenticated user's profile (id, name, email,
     * status, role and permissions).
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success('Authenticated user', new UserProfileResource($request->user()));
    }
}
