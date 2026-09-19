<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    /**
     * Account statuses allowed to authenticate.
     */
    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}
