<?php

namespace App\Support\Business;

use App\Models\User;
use App\Models\Permission;

class BusinessModuleResolver
{
    public function resolve(User $user): array
    {
        if ($user->hasRole('super_admin') || $user->hasRole('super_admin_employee')) {
            return BusinessModules::all();
        }

        if (!$user->hasActiveSubscriptionAccess()) {
            return [];
        }

        $organization = $user->organization;
        $businessType = strtolower((string) ($organization?->business_type ?? 'organisation'));

        $modules = [
            BusinessModules::DASHBOARD,
            BusinessModules::USER_MANAGEMENT,
            BusinessModules::INQUIRY_MANAGEMENT,
            BusinessModules::SETTINGS,
        ];

        if ($this->shouldEnablePropertyManagement($user, $businessType)) {
            $modules[] = BusinessModules::PROPERTY_MANAGEMENT;
        }

        if ($this->shouldEnableServiceManagement($user, $businessType)) {
            $modules[] = BusinessModules::SERVICE_MANAGEMENT;
        }

        return array_values(array_unique($modules));
    }

    public function businessType(User $user): ?string
    {
        return $user->organization?->business_type;
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

        $businessType = strtolower((string) $this->businessType($user));

        return in_array($businessType, ['organisation', 'company'], true)
            || $this->hasDirectPermission($user, ['property.view', 'property.create', 'property.update', 'property.delete'])
            || $user->hasAddonFeature('briksy_exclusive');
    }

    public function isServiceAllowed(User $user): bool
    {
        if ($user->hasRole('super_admin') || $user->hasRole('super_admin_employee')) {
            return true;
        }

        if (!$user->hasActiveSubscriptionAccess()) {
            return false;
        }

        $businessType = strtolower((string) $this->businessType($user));

        return $businessType === 'solo_trader'
            || $this->hasDirectPermission($user, ['service.view', 'service.create', 'service.update', 'service.delete']);
    }

    private function shouldEnablePropertyManagement(User $user, string $businessType): bool
    {
        return in_array($businessType, ['organisation', 'company'], true)
            || $this->hasDirectPermission($user, ['property.view', 'property.create', 'property.update', 'property.delete'])
            || $user->hasAddonFeature('briksy_exclusive');
    }

    private function shouldEnableServiceManagement(User $user, string $businessType): bool
    {
        return $businessType === 'solo_trader'
            || $this->hasDirectPermission($user, ['service.view', 'service.create', 'service.update', 'service.delete']);
    }

    private function hasDirectPermission(User $user, array $permissions): bool
    {
        $user->loadMissing('directPermissions');

        return $user->directPermissions
            ->filter(fn (Permission $permission): bool => $permission->pivot?->effect === 'allow')
            ->pluck('name')
            ->intersect($permissions)
            ->isNotEmpty();
    }
}
