<?php

namespace App\Support\Business;

use App\Models\SubscriptionPlan;
use App\Models\User;

final class PlanCapabilityResolver
{
    public function category(User $user): ?string
    {
        return match ($user->organization?->organizationType?->slug) {
            'property-management' => 'real-estate',
            'solo-traders' => 'trades-professionals',
            default => $user->organization?->organizationType?->slug,
        };
    }

    public function plan(User $user): ?SubscriptionPlan
    {
        return $user->organization?->plan;
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
                'analytics' => isset($features['Analytics Dashboard']),
            ],
            'trades-professionals' => [
                'business_profile' => $legacyTrial || isset($features['Business Profile']),
                'service_areas' => $legacyTrial || isset($features['Service Areas']),
                'service_categories' => $legacyTrial || isset($features['Service Categories']),
                'service_map' => $legacyTrial || isset($features['Map and List Views']),
                'analytics' => isset($features['Performance Analytics']),
            ],
            default => [],
        };
    }
}
