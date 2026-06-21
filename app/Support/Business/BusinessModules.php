<?php

namespace App\Support\Business;

final class BusinessModules
{
    public const DASHBOARD = 'dashboard';
    public const PROPERTY_MANAGEMENT = 'property_management';
    public const SERVICE_MANAGEMENT = 'service_management';
    public const USER_MANAGEMENT = 'user_management';
    public const INQUIRY_MANAGEMENT = 'inquiry_management';
    public const SETTINGS = 'settings';
    public const PLATFORM = 'platform';

    public static function all(): array
    {
        return [
            self::DASHBOARD,
            self::PROPERTY_MANAGEMENT,
            self::SERVICE_MANAGEMENT,
            self::USER_MANAGEMENT,
            self::INQUIRY_MANAGEMENT,
            self::SETTINGS,
            self::PLATFORM,
        ];
    }
}
