<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Hr = 'hr';
    case DepartmentHead = 'department_head';
    case Employee = 'employee';

    /**
     * Display labels for the roles.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Hr => 'HR',
            self::DepartmentHead => 'Department Head',
            self::Employee => 'Employee',
        };
    }

    /**
     * Values accepted by the login role selector (frontend sends these).
     *
     * These are exactly the access roles stored in the `roles` table. "Dean"
     * is an organisational position (see PositionSeeder), not an access
     * role, so it is not a selectable login role.
     */
    public static function loginValues(): array
    {
        return [
            self::Admin->value,
            self::Hr->value,
            self::DepartmentHead->value,
            self::Employee->value,
        ];
    }

    /**
     * Normalize a UI role identifier to the canonical role value stored in
     * the database.
     */
    public static function normalizeForLogin(?string $role): ?string
    {
        if ($role === null) {
            return null;
        }

        $value = strtolower(trim($role));

        if ($value === '') {
            return null;
        }

        return match ($value) {
            'admin', 'administrator' => self::Admin->value,
            'hr', 'human_resources', 'human resources' => self::Hr->value,
            'department_head', 'department head', 'dept_head' => self::DepartmentHead->value,
            'employee', 'staff', 'faculty' => self::Employee->value,
            default => $value,
        };
    }
}
