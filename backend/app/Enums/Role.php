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
}
