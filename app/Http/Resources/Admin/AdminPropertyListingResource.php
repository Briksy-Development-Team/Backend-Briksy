<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPropertyListingResource extends JsonResource
{
    private function normalizeMediaUrl(Request $request, ?string $url, ?string $mediaId = null): ?string
    {
        if (! $url) {
            return null;
        }

        $publicMediaUrl = $mediaId ? rtrim($request->getSchemeAndHttpHost(), '/')."/api/media/{$mediaId}" : null;

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $path = parse_url($url, PHP_URL_PATH);

            if (is_string($path) && str_contains($path, '/storage/')) {
                return $publicMediaUrl ?? rtrim($request->getSchemeAndHttpHost(), '/').$path;
            }

            return $url;
        }

        if (str_starts_with($url, '/')) {
            if (str_contains($url, '/storage/')) {
                return $publicMediaUrl ?? rtrim($request->getSchemeAndHttpHost(), '/').$url;
            }

            return rtrim($request->getSchemeAndHttpHost(), '/').$url;
        }

        return $publicMediaUrl ?? rtrim($request->getSchemeAndHttpHost(), '/').'/'.ltrim($url, '/');
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'generated_id' => $this->generated_id,
            'display_id' => $this->generated_id ?: $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'description' => $this->description,
            'address' => $this->address,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'full_address' => $this->full_address,
            'formatted_address' => $this->formatted_address,
            'place_id' => $this->place_id,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'rating' => (float) $this->avg_prop_rating,
            'suburb' => $this->suburb,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'location_verified' => (bool) $this->location_verified,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_by' => $this->reviewed_by,
            'reviewer' => $this->whenLoaded('reviewer', function (): ?array {
                return $this->reviewer ? [
                    'id' => $this->reviewer?->id,
                    'name' => $this->reviewer?->name,
                    'email' => $this->reviewer?->email,
                ] : null;
            }),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'published_at' => $this->published_at?->toISOString(),
            'location_verified_by' => $this->location_verified_by,
            'location_verifier' => $this->whenLoaded('locationVerifier', function (): ?array {
                return $this->locationVerifier ? [
                    'id' => $this->locationVerifier?->id,
                    'name' => $this->locationVerifier?->name,
                    'email' => $this->locationVerifier?->email,
                ] : null;
            }),
            'location_verified_at' => $this->location_verified_at?->toISOString(),
            'organization_id' => $this->org_id,
            'property_type_id' => $this->property_type_id,
            'property_type' => $this->whenLoaded('propertyType', function (): ?array {
                return $this->propertyType ? [
                    'id' => $this->propertyType?->id,
                    'name' => $this->propertyType?->name,
                    'slug' => $this->propertyType?->slug,
                ] : null;
            }),
            'images' => $this->whenLoaded('media', function () use ($request): array {
                return $this->media
                    ->where('media_type', 'image')
                    ->map(fn ($media): array => [
                        'id' => $media->id,
                        'url' => $this->normalizeMediaUrl($request, $media->file_url, (string) $media->id),
                        'is_primary' => (bool) $media->is_primary,
                        'sort_order' => (int) $media->sort_order,
                    ])
                    ->values()
                    ->all();
            }),
            'videos' => $this->whenLoaded('media', function () use ($request): array {
                return $this->media
                    ->where('media_type', 'video')
                    ->map(fn ($media): array => [
                        'id' => $media->id,
                        'url' => $this->normalizeMediaUrl($request, $media->file_url, (string) $media->id),
                        'is_primary' => (bool) $media->is_primary,
                        'sort_order' => (int) $media->sort_order,
                    ])
                    ->values()
                    ->all();
            }),
            'organization' => $this->whenLoaded('organization', function (): array {
                return [
                    'id' => $this->organization?->id,
                    'name' => $this->organization?->name,
                    'slug' => $this->organization?->slug,
                    'is_verified' => (bool) $this->organization?->is_verified,
                ];
            }),
            'creator' => $this->whenLoaded('creator', function (): array {
                return [
                    'id' => $this->creator?->id,
                    'name' => $this->creator?->name,
                    'email' => $this->creator?->email,
                ];
            }),
            'timeline_events' => $this->whenLoaded('activityLogs', function (): array {
                return $this->activityLogs
                    ->map(function ($log): array {
                        $metadata = $log->metadata ?? [];

                        return [
                            'id' => $log->id,
                            'action' => $log->action,
                            'title' => $metadata['title'] ?? $log->action ?? 'Update',
                            'description' => $log->description,
                            'comment' => $metadata['comment'] ?? null,
                            'user_name' => $log->user_name,
                            'user_role' => $log->user_role,
                            'created_at' => $log->created_at?->toISOString(),
                        ];
                    })
                    ->values()
                    ->all();
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
