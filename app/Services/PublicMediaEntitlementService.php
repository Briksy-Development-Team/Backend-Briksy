<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Http\Exceptions\HttpResponseException;
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

        if ($subscription && !$subscription->relationLoaded('plan')) {
            $subscription->load('plan');
        }

        if (!$subscription || !in_array($subscription->status, ['active', 'trialing'], true)) {
            return 0;
        }

        if (($subscription->current_period_start && $subscription->current_period_start->isFuture()) || ($subscription->current_period_end && $subscription->current_period_end->isPast())) {
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

    /**
     * Reject an upload before a file is written to storage. Existing media is
     * deliberately counted but never removed, including after a downgrade.
     */
    public function assertUploadAllowed(
        ?Organization $organization,
        string $entity,
        string $mediaType,
        int $existingCount,
        int $newCount,
    ): void {
        if ($newCount <= 0) {
            return;
        }

        $limit = $this->limitFor($organization, $entity, $mediaType);
        if ($limit === null) {
            return;
        }

        if ($mediaType === 'video' && $limit === 0) {
            $this->uploadError(
                'PLAN_VIDEO_NOT_INCLUDED',
                'Video uploads are not included in your current plan.'
            );
        }

        if ($existingCount + $newCount > $limit) {
            $label = $mediaType === 'video' ? 'videos' : 'images';
            $this->uploadError(
                $mediaType === 'video' ? 'PLAN_VIDEO_LIMIT_REACHED' : 'PLAN_IMAGE_LIMIT_REACHED',
                sprintf('You have reached the maximum number of %s allowed by your current plan.', $label)
            );
        }
    }

    private function uploadError(string $code, string $message): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
        ], 422));
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
