<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Collection;

class PublicMediaEntitlementService
{
    /**
     * Resolve the public media limit for an organisation.
     *
     * A null limit is the existing feature convention for unlimited. An
     * organisation without a current subscription has no public media
     * entitlement; uploaded media is not deleted.
     */
    public function limitFor(?Organization $organization, string $entity, string $mediaType): int|null
    {
        if (! $organization) {
            return 0;
        }

        $subscription = $organization->relationLoaded('currentSubscription')
            ? $organization->getRelation('currentSubscription')
            : $organization->currentSubscription()->with('plan')->first();

        if ($subscription && (($subscription->current_period_start && $subscription->current_period_start->isFuture()) || ($subscription->current_period_end && $subscription->current_period_end->isPast()))) {
            $subscription = null;
        }

        $plan = $subscription?->plan;
        if (! $plan) {
            return 0;
        }

        $featureNames = $this->featureNames($entity, $mediaType);
        $feature = collect($plan->features ?? [])->first(function (mixed $feature) use ($featureNames): bool {
            return in_array($this->normalise($feature['name'] ?? ''), $featureNames, true);
        });

        if (! is_array($feature)) {
            // Existing property plans pre-date media entitlements. Keep their
            // current public behaviour until explicit media features exist.
            return null;
        }

        if (! ($feature['enabled'] ?? false)) {
            return 0;
        }

        return array_key_exists('value', $feature) && $feature['value'] === null
            ? null
            : max(0, (int) ($feature['value'] ?? 0));
    }

    public function take(Collection $media, ?int $limit): Collection
    {
        $media = $media->sortBy([
            ['is_primary', 'desc'],
            ['sort_order', 'asc'],
            ['created_at', 'asc'],
        ]);

        if ($limit === null) {
            return $media->values();
        }

        return $media->take($limit)->values();
    }

    private function featureNames(string $entity, string $mediaType): array
    {
        $entity = $this->normalise($entity);
        $mediaType = $this->normalise($mediaType);

        $names = [
            "{$entity} {$mediaType}",
            "{$entity} {$mediaType}s",
            "{$entity} media {$mediaType}",
            "{$entity} media {$mediaType}s",
        ];

        if ($entity === 'service' && $mediaType === 'image') {
            $names[] = 'portfolio photos';
            $names[] = 'portfolio images';
        }

        if ($entity === 'service' && $mediaType === 'video') {
            $names[] = 'portfolio videos';
        }

        return array_values(array_unique(array_map(fn (string $name): string => $this->normalise($name), $names)));
    }

    private function normalise(string $value): string
    {
        return strtolower(trim(preg_replace('/\\s+/', ' ', $value) ?? ''));
    }
}
