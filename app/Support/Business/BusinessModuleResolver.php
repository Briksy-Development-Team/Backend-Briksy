<?php

namespace App\Support\Business;

use App\Models\User;

class BusinessModuleResolver
{
    public function __construct(private readonly PlanCapabilityResolver $capabilities)
    {
    }

    public function resolve(User $user): array
    {
        if ($user->hasRole('super_admin') || $user->hasRole('super_admin_employee')) {
            return BusinessModules::all();
        }

        if (!$user->hasActiveSubscriptionAccess()) {
            return [];
        }

        $category = $this->category($user);
        $capabilities = $this->capabilities->capabilities($user);

        $modules = [
            BusinessModules::DASHBOARD,
            BusinessModules::USER_MANAGEMENT,
            BusinessModules::INQUIRY_MANAGEMENT,
            BusinessModules::SETTINGS,
        ];

        if ($category === 'real-estate' && ($capabilities['property_management'] ?? false)) {
            $modules[] = BusinessModules::PROPERTY_MANAGEMENT;
        }

        if ($category === 'trades-professionals' && ($capabilities['business_profile'] ?? false)) {
            $modules[] = BusinessModules::SERVICE_MANAGEMENT;
        }

        if ($category === 'buyers-agent' && ($capabilities['buyer_briefs'] ?? false)) {
            $modules[] = BusinessModules::BUYER_MANAGEMENT;
        }

        if ($category === 'builders' && ($capabilities['projects'] ?? false)) {
            $modules[] = BusinessModules::BUILDER_MANAGEMENT;
        }

        return array_values(array_unique($modules));
    }

    public function businessType(User $user): ?string
    {
        return $user->organization?->business_type;
    }

    public function category(User $user): ?string
    {
        return $this->capabilities->category($user);
    }

    public function capabilities(User $user): array
    {
        return $this->capabilities->capabilities($user);
    }

    public function verificationStatus(User $user): ?string
    {
        return $user->organization?->business_verification_status;
    }

    public function isPropertyAllowed(User $user): bool
    {
        if ($user->hasRole('super_admin') || $user->hasRole('super_admin_employee')) {
            return true;
        }

        if (!$user->hasActiveSubscriptionAccess()) {
            return false;
        }

        return $this->category($user) === 'real-estate' && ($this->capabilities($user)['property_management'] ?? false);
    }

    public function isServiceAllowed(User $user): bool
    {
        if ($user->hasRole('super_admin') || $user->hasRole('super_admin_employee')) {
            return true;
        }

        if (!$user->hasActiveSubscriptionAccess()) {
            return false;
        }

        return $this->category($user) === 'trades-professionals' && ($this->capabilities($user)['business_profile'] ?? false);
    }

}
