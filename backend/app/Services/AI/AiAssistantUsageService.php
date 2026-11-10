<?php

namespace App\Services\AI;

use App\Models\AiAssistantDailyUsage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class AiAssistantUsageService
{
    /**
     * @return array{limit:int, used:int, remaining:int, resets_at:string, allowed:bool, role:string|null}
     */
    public function status(User $user): array
    {
        $limit = $this->dailyLimit($user);
        $date = $this->usageDate();
        $used = 0;
        $allowed = $this->isRoleAllowed($user);

        if ($allowed && $limit > 0) {
            $usage = AiAssistantDailyUsage::query()
                ->where('user_id', $user->id)
                ->whereDate('usage_date', $date)
                ->first();
            $used = (int) ($usage?->request_count ?? 0);
        }

        $remaining = $limit > 0 ? max(0, $limit - $used) : 0;

        return [
            'allowed' => $allowed,
            'role' => $user->roleName(),
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'resets_at' => $this->resetsAt()->toIso8601String(),
        ];
    }

    /**
     * Consume one AI request if under limit. Throws 429 when limit reached.
     * Call only after role/scope/validation guards pass; blocked/invalid
     * requests must not consume usage.
     *
     * @return array<string, mixed>
     */
    public function consume(User $user): array
    {
        if (! $this->isRoleAllowed($user)) {
            throw new TooManyRequestsHttpException(null, 'JPC AIDE is available only to authorized Admin and Employee users.');
        }

        $limit = $this->dailyLimit($user);
        if ($limit <= 0) {
            throw new TooManyRequestsHttpException(null, 'JPC AIDE is not available for your account role.');
        }

        $date = $this->usageDate()->toDateString();

        return DB::transaction(function () use ($user, $limit, $date) {
            $usage = AiAssistantDailyUsage::query()
                ->where('user_id', $user->id)
                ->whereDate('usage_date', $date)
                ->lockForUpdate()
                ->first();

            if (! $usage) {
                $usage = AiAssistantDailyUsage::create([
                    'user_id' => $user->id,
                    'usage_date' => $date,
                    'request_count' => 0,
                ]);
                $usage = AiAssistantDailyUsage::query()->whereKey($usage->id)->lockForUpdate()->firstOrFail();
            }

            $used = (int) $usage->request_count;
            if ($used >= $limit) {
                throw new TooManyRequestsHttpException(
                    $this->secondsUntilReset(),
                    'You have reached your daily AI usage limit. Please try again tomorrow.'
                );
            }

            $usage->request_count = $used + 1;
            $usage->save();

            $newUsed = (int) $usage->request_count;

            return [
                'limit' => $limit,
                'used' => $newUsed,
                'remaining' => max(0, $limit - $newUsed),
                'resets_at' => $this->resetsAt()->toIso8601String(),
            ];
        }, 3);
    }

    public function isRoleAllowed(User $user): bool
    {
        $allowed = (array) config('ai.assistant.allowed_roles', ['admin', 'employee']);
        return $user->hasAnyRole($allowed);
    }

    public function dailyLimit(User $user): int
    {
        $role = $user->roleName();
        $limits = (array) config('ai.assistant.daily_limits', []);

        if ($role && array_key_exists($role, $limits)) {
            return max(0, (int) $limits[$role]);
        }

        // Admin role name may vary; treat administrator-like roles conservatively.
        if ($user->hasAnyRole(['admin', 'administrator'])) {
            return max(0, (int) ($limits['admin'] ?? 30));
        }
        if ($user->hasAnyRole(['employee'])) {
            return max(0, (int) ($limits['employee'] ?? 20));
        }

        return 0;
    }

    private function usageDate(): Carbon
    {
        return Carbon::now($this->timezone())->startOfDay();
    }

    private function resetsAt(): Carbon
    {
        return Carbon::now($this->timezone())->addDay()->startOfDay();
    }

    private function secondsUntilReset(): int
    {
        return max(1, Carbon::now($this->timezone())->diffInSeconds($this->resetsAt()));
    }

    private function timezone(): string
    {
        $tz = (string) config('ai.assistant.reset_timezone', config('app.timezone', 'UTC'));
        return $tz !== '' ? $tz : 'UTC';
    }
}
