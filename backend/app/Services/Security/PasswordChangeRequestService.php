<?php

namespace App\Services\Security;

use App\Models\PasswordChangeRequest;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Password change requests — approval workflow for employee-initiated password
 * changes.
 *
 * An employee submits their current password plus the desired new password; the
 * new password is hashed and held on a pending request. The account password is
 * only changed when an administrator approves the request, so a compromised or
 * mistaken change cannot take effect without review.
 */
class PasswordChangeRequestService
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * Submit a new password change request for admin approval.
     *
     * @return array<string, mixed>
     */
    public function request(User $user, string $currentPassword, string $newPassword, ?string $reason = null): array
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $alreadyPending = PasswordChangeRequest::query()
            ->where('user_id', $user->id)
            ->pending()
            ->exists();

        if ($alreadyPending) {
            throw ValidationException::withMessages([
                'password' => ['You already have a password change request awaiting approval.'],
            ]);
        }

        $request = DB::transaction(function () use ($user, $newPassword, $reason): PasswordChangeRequest {
            $request = PasswordChangeRequest::create([
                'user_id' => $user->id,
                'requested_by' => $user->id,
                'proposed_password' => Hash::make($newPassword),
                'reason' => $reason,
                'status' => PasswordChangeRequest::STATUS_PENDING,
            ]);

            $this->activityLogger->log(
                'security.password_change.requested',
                'Submitted a password change request.',
                $user,
                $request,
                ['password_change_request_id' => $request->id],
            );

            return $request;
        });

        return $this->present($request);
    }

    /**
     * The requester's most recent request, or null when they have none.
     *
     * @return array<string, mixed>|null
     */
    public function latestFor(User $user): ?array
    {
        $request = PasswordChangeRequest::query()
            ->where('user_id', $user->id)
            ->with(['requestedBy:id,name', 'reviewedBy:id,name'])
            ->orderByDesc('id')
            ->first();

        return $request === null ? null : $this->present($request);
    }

    /**
     * Paginated password change requests for the admin review queue.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return PasswordChangeRequest::query()
            ->with([
                'user:id,name,email',
                'user.employee:id,user_id,employee_number,department_id',
                'user.employee.department:id,name',
                'requestedBy:id,name',
                'reviewedBy:id,name',
            ])
            ->when(
                ! empty($filters['status']) && in_array($filters['status'], [
                    PasswordChangeRequest::STATUS_PENDING,
                    PasswordChangeRequest::STATUS_APPROVED,
                    PasswordChangeRequest::STATUS_REJECTED,
                ], true),
                fn (Builder $q) => $q->where('status', $filters['status']),
            )
            ->when(
                ! empty($filters['search']),
                function (Builder $q) use ($filters) {
                    $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                    $q->where(function (Builder $inner) use ($term) {
                        $inner->whereHas('user', function (Builder $u) use ($term) {
                            $u->where('name', 'like', $term)->orWhere('email', 'like', $term);
                        });
                    });
                },
            )
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (PasswordChangeRequest $r) => $this->present($r));
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $base = PasswordChangeRequest::query();

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', PasswordChangeRequest::STATUS_PENDING)->count(),
            'approved' => (clone $base)->where('status', PasswordChangeRequest::STATUS_APPROVED)->count(),
            'rejected' => (clone $base)->where('status', PasswordChangeRequest::STATUS_REJECTED)->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(int $id): ?array
    {
        $request = PasswordChangeRequest::query()
            ->with([
                'user:id,name,email',
                'user.employee:id,user_id,employee_number,department_id',
                'user.employee.department:id,name',
                'requestedBy:id,name',
                'reviewedBy:id,name',
            ])
            ->find($id);

        return $request === null ? null : $this->present($request);
    }

    /**
     * Approve a request: apply the stored password hash and record the decision.
     *
     * @return array<string, mixed>
     */
    public function approve(User $admin, int $id, ?string $note): array
    {
        $request = $this->find($id);
        $this->assertPending($request);

        DB::transaction(function () use ($request, $admin, $note): void {
            $target = $request->user;

            if ($target === null) {
                throw ValidationException::withMessages(['user' => ['The account no longer exists.']]);
            }

            $target->update(['password' => $request->proposed_password]);

            $request->forceFill([
                'status' => PasswordChangeRequest::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => Carbon::now(),
                'decision_note' => $note,
                'applied_at' => Carbon::now(),
            ])->save();

            $this->activityLogger->log(
                'security.password_change.approved',
                'Approved a password change request.',
                $admin,
                $request,
                ['password_change_request_id' => $request->id, 'user_id' => $target->id],
            );
        });

        return $this->present($request->fresh(['user', 'reviewedBy']));
    }

    /**
     * Reject a request, recording the decision without changing the password.
     *
     * @return array<string, mixed>
     */
    public function reject(User $admin, int $id, ?string $note): array
    {
        $request = $this->find($id);
        $this->assertPending($request);

        DB::transaction(function () use ($request, $admin, $note): void {
            $request->forceFill([
                'status' => PasswordChangeRequest::STATUS_REJECTED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => Carbon::now(),
                'decision_note' => $note,
            ])->save();

            $this->activityLogger->log(
                'security.password_change.rejected',
                'Rejected a password change request.',
                $admin,
                $request,
                ['password_change_request_id' => $request->id],
            );
        });

        return $this->present($request->fresh(['user', 'reviewedBy']));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PasswordChangeRequest $request): array
    {
        $employee = $request->relationLoaded('user') ? $request->user?->employee : null;

        return [
            'id' => $request->id,
            'status' => $request->status,
            'reason' => $request->reason,
            'requester_name' => $request->relationLoaded('user') && $request->user
                ? $request->user->name
                : null,
            'requester_email' => $request->relationLoaded('user') && $request->user
                ? $request->user->email
                : null,
            'employee_number' => $employee?->employee_number,
            'department_name' => $employee?->department?->name,
            'requested_by' => $request->relationLoaded('requestedBy') && $request->requestedBy
                ? $request->requestedBy->name
                : null,
            'reviewed_by' => $request->relationLoaded('reviewedBy') && $request->reviewedBy
                ? $request->reviewedBy->name
                : null,
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'decision_note' => $request->decision_note,
            'applied_at' => $request->applied_at?->toIso8601String(),
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }

    private function find(int $id): PasswordChangeRequest
    {
        $request = PasswordChangeRequest::with('user')->find($id);

        if ($request === null) {
            abort(404, 'Password change request not found.');
        }

        return $request;
    }

    private function assertPending(PasswordChangeRequest $request): void
    {
        if ($request->status !== PasswordChangeRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['This password change request has already been reviewed.'],
            ]);
        }
    }
}
