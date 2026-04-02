<?php

namespace App\Support\Auth;

final class AdminAbilities
{
    public const ADMIN = 'admin';
    public const ADMIN_STAFF = 'admin_staff';

    public const ADMIN_ONLY = [
        self::ADMIN,
    ];

    public const ADMIN_AND_STAFF = [
        self::ADMIN,
        self::ADMIN_STAFF,
    ];

    public static function middleware(array $abilities): string
    {
        return 'ability:' . implode(',', $abilities);
    }
}
