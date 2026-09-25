<?php

namespace App\Models;

use App\Enums\Role as RoleEnum;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'status',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * The role assigned to this user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Roles granted to this user via the role_user pivot.
     *
     * The application still reads users.role_id for Phase 2 compatibility; this
     * many-to-many relationship is the target for the 3.1-D role migration.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /**
     * The employee profile linked to this account.
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Annual plans this user created.
     */
    public function createdAnnualPlans(): HasMany
    {
        return $this->hasMany(AnnualPlan::class, 'created_by');
    }

    /**
     * Annual plans this user approved.
     */
    public function approvedAnnualPlans(): HasMany
    {
        return $this->hasMany(AnnualPlan::class, 'approved_by');
    }

    /**
     * Department plans this user created.
     */
    public function createdDepartmentPlans(): HasMany
    {
        return $this->hasMany(DepartmentPlan::class, 'created_by');
    }

    /**
     * Department plans this user approved.
     */
    public function approvedDepartmentPlans(): HasMany
    {
        return $this->hasMany(DepartmentPlan::class, 'approved_by');
    }

    /**
     * Tasks this user created.
     */
    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    /**
     * Tasks this user last updated.
     */
    public function updatedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'updated_by');
    }

    /**
     * Task assignments issued by this user.
     */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(TaskAssignment::class, 'assigned_by');
    }

    /**
     * Task reviews performed by this user.
     */
    public function reviewedTasks(): HasMany
    {
        return $this->hasMany(TaskReview::class, 'reviewed_by');
    }

    /**
     * User-facing in-app notifications.
     *
     * Deliberately overrides the Notifiable trait's morphMany: the JPC
     * `notifications` table is a custom inbox (user_id, type, title, message)
     * and not Laravel's database-notification morph schema.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Authentication activity records for this user.
     */
    public function loginActivities(): HasMany
    {
        return $this->hasMany(LoginActivity::class);
    }

    /**
     * The role name (e.g. "admin") or null when unassigned.
     */
    public function roleName(): ?string
    {
        return $this->role?->name;
    }

    /**
     * Permission names granted through the user's role.
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return $this->role?->permissions->pluck('name')->values()->all() ?? [];
    }

    /**
     * Whether the user holds the given role.
     */
    public function hasRole(string|RoleEnum $role): bool
    {
        $target = $role instanceof RoleEnum ? $role->value : $role;

        return $this->roleName() === $target;
    }

    /**
     * Assign a role by name or enum value, keeping both users.role_id and the
     * role_user pivot in sync. The role record is created when it does not
     * exist yet (useful for tests and fresh installations).
     */
    public function assignRole(string|RoleEnum $role): static
    {
        $name = $role instanceof RoleEnum ? $role->value : $role;

        $roleModel = Role::query()->firstOrCreate(['name' => $name]);

        $this->forceFill(['role_id' => $roleModel->id])->save();

        $this->roles()->syncWithoutDetaching([$roleModel->id]);

        return $this;
    }

    /**
     * Whether the user holds any of the given roles.
     *
     * @param  array<int, string|RoleEnum>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the user has the given permission.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissionNames(), true);
    }

    /**
     * Whether the user holds any of the given permissions.
     *
     * @param  list<string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the account is allowed to authenticate.
     */
    public function canAuthenticate(): bool
    {
        return $this->status?->canAuthenticate() ?? false;
    }
}
