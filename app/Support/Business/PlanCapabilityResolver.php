<?php

namespace App\Support\Business;

use App\Models\SubscriptionPlan;
use App\Models\Service;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;

final class PlanCapabilityResolver
{
    /**
     * The plan JSON remains the source of truth, while these stable keys keep
     * controllers and clients independent of presentation labels.
     */
    private const FEATURE_ALIASES = [
        'verified_badge' => ['Verified Badge'],
        'business_profile' => ['Business Profile'],
        'service_management' => ['Business Profile', 'Service Management'],
        'service_enquiry' => ['Lead History', 'Service Enquiry', 'Buyer Enquiry Forms'],
        'service_areas' => ['Service Areas'],
        'service_categories' => ['Service Categories'],
        'active_services' => ['Active Services', 'Services'],
        'property_listings' => ['Property Listings', 'Properties'],
        'staff_members' => ['Team Members', 'Staff Seats'],
        'maximum_images' => ['Portfolio Photos', 'Service Images', 'Property Images', 'Project Images'],
        'maximum_videos' => ['Portfolio Videos', 'Service Videos', 'Property Videos', 'Project Videos'],
        'video_upload' => ['Portfolio Videos', 'Video Upload', 'Project Videos'],
        'projects' => ['Projects'],
        'project_listings' => ['Project Listings'],
        'buyer_briefs' => ['Buyer Briefs'],
        'promo_offers' => ['Promo Offers'],
        'analytics' => ['Analytics Dashboard', 'Performance Analytics'],
    ];
    public function category(User $user): ?string
    {
        return match ($user->organization?->organizationType?->slug) {
            'property-management' => 'real-estate',
            'solo-traders' => 'trades-professionals',
            default => $user->organization?->organizationType?->slug,
        };
    }

    public function planFamily(User $user): ?string
    {
        return match ($this->category($user)) {
            'real-estate' => 'property_owner',
            'buyers-agent' => 'buyers_agent',
            'builders' => 'builders',
            'trades-professionals' => 'trades_professional',
            default => null,
        };
    }

    public function plan(User $user): ?SubscriptionPlan
    {
        return $user->organization?->plan;
    }

    /**
     * Resolve public plan entitlement without requiring an authenticated user.
     * Verification remains a separate organization concern.
     */
    public function organizationFeatureEnabled(Organization $organization, string $key): bool
    {
        $subscription = $organization->relationLoaded('currentSubscription')
            ? $organization->getRelation('currentSubscription')
            : $organization->currentSubscription()->with('plan')->first();

        if (!$subscription || !in_array($subscription->status, ['active', 'trialing'], true)) {
            return false;
        }

        if (($subscription->current_period_start?->isFuture() ?? false)
            || ($subscription->current_period_end?->isPast() ?? false)) {
            return false;
        }

        $aliases = self::FEATURE_ALIASES[$key] ?? [$key];
        return collect($subscription->plan?->features ?? [])->contains(
            fn (array $feature): bool => collect($aliases)->contains(
                fn (string $alias): bool => strcasecmp((string) ($feature['name'] ?? ''), $alias) === 0
            ) && (bool) ($feature['enabled'] ?? false)
        );
    }

    public function feature(User $user, string $name): array
    {
        $plan = $this->plan($user);
        $feature = collect($plan?->features ?? [])->first(fn (array $item): bool => strcasecmp((string) ($item['name'] ?? ''), $name) === 0);

        return [
            'name' => $name,
            'enabled' => (bool) ($feature['enabled'] ?? false),
            'value' => array_key_exists('value', $feature ?? []) ? $feature['value'] : null,
        ];
    }

    public function featureByKey(User $user, string $key): array
    {
        $aliases = self::FEATURE_ALIASES[$key] ?? [$key];
        foreach ($aliases as $alias) {
            $feature = $this->feature($user, $alias);
            if ($feature['enabled'] || $feature['value'] !== null) {
                return ['key' => $key, 'configured' => true] + $feature;
            }

            if ($user->organization?->plan && collect($user->organization->plan->features ?? [])
                ->contains(fn (array $item): bool => strcasecmp((string) ($item['name'] ?? ''), $alias) === 0)) {
                return ['key' => $key, 'configured' => true] + $feature;
            }
        }

        return [
            'key' => $key,
            'configured' => false,
            'name' => $aliases[0] ?? $key,
            'enabled' => false,
            'value' => null,
        ];
    }

    public function limit(User $user, string $key, ?int $fallback = null): ?int
    {
        $feature = $this->featureByKey($user, $key);
        if (!$feature['enabled']) {
            return 0;
        }

        return $feature['value'] === null ? $fallback : max(0, (int) $feature['value']);
    }

    public function resolved(User $user): array
    {
        $organization = $user->organization;
        $isGlobal = $user->isSuperAdmin() || $user->isGlobalStaff();
        $subscription = $organization?->currentSubscription;
        $status = $isGlobal
            ? 'active'
            : ($user->subscriptionStatus());
        $active = $isGlobal || in_array($status, ['active', 'trialing'], true);
        $plan = $organization?->plan;

        $features = [];
        foreach (array_keys(self::FEATURE_ALIASES) as $key) {
            $feature = $this->featureByKey($user, $key);
            $features[$key] = [
                'enabled' => $active && (bool) $feature['enabled'],
                'value' => $active ? $feature['value'] : null,
                'configured' => (bool) ($feature['configured'] ?? false),
            ];
        }

        $features['service_management']['enabled'] = $active && ($this->capabilities($user)['business_profile'] ?? false);
        $features['service_enquiry']['enabled'] = $active && ($this->capabilities($user)['service_enquiry'] ?? false);

        return [
            'status' => $status,
            'active' => $active,
            'organization_id' => $organization?->id,
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'family' => $plan->plan_family,
                'currency' => $plan->currency ?? 'AUD',
                'monthly_price' => $plan->monthly_price !== null ? (float) $plan->monthly_price : null,
                'yearly_price' => $plan->discountedYearlyPrice(),
            ] : null,
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'current_period_start' => $subscription->current_period_start?->toISOString(),
                'current_period_end' => $subscription->current_period_end?->toISOString(),
            ] : null,
            'limits' => [
                'property_listings' => $active
                    ? (($this->featureByKey($user, 'property_listings')['value'] ?? null) !== null
                        ? (int) $this->featureByKey($user, 'property_listings')['value']
                        : (int) ($plan?->property_limit ?? 0))
                    : 0,
                'staff_members' => $active
                    ? (($this->featureByKey($user, 'staff_members')['value'] ?? null) !== null
                        ? (int) $this->featureByKey($user, 'staff_members')['value']
                        : (int) ($plan?->staff_seat_limit ?? 0))
                    : 0,
                'active_services' => $features['active_services']['value'],
                'images' => $features['maximum_images']['value'],
                'videos' => $features['maximum_videos']['value'],
                'service_areas' => $features['service_areas']['value'],
                'service_categories' => $features['service_categories']['value'],
                'projects' => $features['projects']['value'],
                'buyer_briefs' => $features['buyer_briefs']['value'],
                'promo_offers' => $features['promo_offers']['value'],
            ],
            'features' => $features,
            'capabilities' => $this->capabilities($user),
        ];
    }

    public function enabledFeatures(User $user): array
    {
        return collect($this->plan($user)?->features ?? [])
            ->filter(fn (array $feature): bool => (bool) ($feature['enabled'] ?? false))
            ->mapWithKeys(fn (array $feature): array => [
                (string) ($feature['name'] ?? '') => $feature['value'] ?? true,
            ])
            ->filter(fn (mixed $value, string $name): bool => $name !== '')
            ->all();
    }

    public function capabilities(User $user): array
    {
        $category = $this->category($user);
        $features = $this->enabledFeatures($user);

        // Compatibility for pre-taxonomy tenant accounts created during the
        // trial flow. Canonical four-category accounts always require a plan
        // feature; these aliases are not used by demo organizations.
        $legacyTrial = !$this->plan($user) && in_array($user->organization?->organizationType?->slug, ['property-management', 'solo-traders'], true);

        return match ($category) {
            'real-estate' => [
                'property_management' => $legacyTrial || isset($features['Property Listings']),
                'property_map' => $legacyTrial || isset($features['Map and List Views']),
                'property_enquiries' => $legacyTrial || isset($features['Buyer Enquiry Forms']),
                'analytics' => isset($features['Analytics Dashboard']),
            ],
            'buyers-agent' => [
                'buyer_briefs' => isset($features['Buyer Briefs']),
                'saved_searches' => isset($features['Saved Searches']),
                'property_shortlists' => isset($features['Property Shortlists']),
                'buyer_enquiries' => isset($features['Lead Inbox']),
                'analytics' => isset($features['Analytics Dashboard']),
            ],
            'builders' => [
                'projects' => isset($features['Projects']),
                'project_listings' => isset($features['Project Listings']),
                'tender_management' => isset($features['Tender Management']),
                'site_notes' => isset($features['Site Notes']),
                'enquiries' => isset($features['Lead Inbox']),
                'analytics' => isset($features['Analytics Dashboard']),
            ],
            'trades-professionals' => [
                'business_profile' => $legacyTrial || isset($features['Business Profile']),
                'service_enquiry' => $legacyTrial || isset($features['Lead History']) || isset($features['Service Enquiry']),
                'service_areas' => $legacyTrial || isset($features['Service Areas']),
                'service_categories' => $legacyTrial || isset($features['Service Categories']),
                'service_map' => $legacyTrial || isset($features['Map and List Views']),
                'analytics' => isset($features['Performance Analytics']),
            ],
            default => [],
        };
    }
    /**
     * Return the existing service taxonomy entries available under the
     * organisation's active plan. The Service Categories feature controls
     * how many catalog entries may be used.
     */
    public function serviceCatalog(User $user): Collection
    {
        if ($user->isSuperAdmin() || $user->isGlobalStaff()) {
            return Service::query()->where('is_active', true)->orderBy('name')->get();
        }

        return $user->organization ? $this->serviceCatalogForOrganization($user->organization) : collect();
    }

    public function serviceCatalogForOrganization(Organization $organization): Collection
    {
        $subscription = $organization->currentSubscription;
        $active = in_array($subscription?->status, ['active', 'trialing'], true)
            || ($organization->trial_ends_at && now()->lessThanOrEqualTo($organization->trial_ends_at));
        if (!$active || !$organization->type_id || !$organization->plan) {
            return collect();
        }

        $feature = collect($organization->plan->features ?? [])->first(
            fn (array $item): bool => strcasecmp((string) ($item['name'] ?? ''), 'Service Categories') === 0
        );
        if (!($feature['enabled'] ?? false)) {
            return collect();
        }

        $limit = array_key_exists('value', $feature) && $feature['value'] !== null ? max(0, (int) $feature['value']) : null;
        $configuredSlugs = collect(config('service_categories', []))
            ->pluck('slug')
            ->filter()
            ->values();
        $query = Service::query()
            ->where('is_active', true)
            ->where('type_id', $organization->type_id)
            ->when($configuredSlugs->isNotEmpty(), fn ($serviceQuery) => $serviceQuery->whereIn('slug', $configuredSlugs))
            ->orderBy('name');

        return $limit === null ? $query->get() : $query->limit($limit)->get();
    }

    public function serviceCategoryAllowedForOrganization(Organization $organization, ?string $value): bool
    {
        // Legacy trial organisations predate plan-backed category entitlements.
        if (!$organization->plan_id) {
            return true;
        }

        $feature = collect($organization->plan?->features ?? [])->first(
            fn (array $item): bool => strcasecmp((string) ($item['name'] ?? ''), 'Service Categories') === 0
        );
        if ($feature === null) {
            return true;
        }
        if (!($feature['enabled'] ?? false)) {
            return false;
        }

        $needle = mb_strtolower(trim((string) $value));
        return $needle !== '' && $this->serviceCatalogForOrganization($organization)->contains(
            fn (Service $service): bool => in_array($needle, array_filter([
                mb_strtolower((string) $service->slug),
                mb_strtolower((string) $service->category),
                mb_strtolower((string) $service->name),
            ]), true)
        );
    }

    public function resolveServiceCatalogEntry(User $user, ?string $value): ?Service
    {
        $needle = mb_strtolower(trim((string) $value));
        if ($needle === '') {
            return null;
        }

        return $this->serviceCatalog($user)->first(function (Service $service) use ($needle): bool {
            return in_array($needle, array_filter([
                mb_strtolower((string) $service->slug),
                mb_strtolower((string) $service->category),
                mb_strtolower((string) $service->name),
            ]), true);
        });
    }

}
