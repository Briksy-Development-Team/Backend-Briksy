<?php

namespace App\Http\Resources\Seeker;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyListingResource extends JsonResource
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
            'description' => $this->description,
            'address' => $this->address,
            'full_address' => $this->full_address,
            'status' => $this->status,
            'rating' => (float) $this->avg_prop_rating,
            'location_verified' => (bool) $this->location_verified,
            'location' => [
                'suburb' => $this->suburb,
                'postcode' => $this->postcode,
                'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
                'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            ],
            'organization' => $this->whenLoaded('organization', function (): array {
                return [
                    'id' => $this->organization?->id,
                    'name' => $this->organization?->name,
                    'slug' => $this->organization?->slug,
                    'type' => $this->organization?->organizationType?->name,
                    'is_verified' => (bool) $this->organization?->is_verified,
                ];
            }),
            'media' => $this->whenLoaded('media', function () use ($request): array {
                return $this->media
                    ->map(fn ($media): array => [
                        'id' => $media->id,
                        'url' => $this->normalizeMediaUrl($request, $media->file_url, (string) $media->id),
                        'type' => $media->media_type,
                        'is_primary' => (bool) $media->is_primary,
                    ])
                    ->values()
                    ->all();
            }),
            'has_briksy_exclusive_offer' => $this->whenLoaded('offers', fn (): bool => $this->offers->contains(fn ($offer): bool => (bool) $offer->is_active), false),
            'briksy_exclusive_offers' => $this->whenLoaded('offers', fn (): array => $this->offers
                ->where('is_active', true)
                ->map(fn ($offer): array => [
                    'id' => $offer->id,
                    'title' => $offer->title,
                    'tag_label' => $offer->tag_label,
                    'summary' => $offer->summary,
                    'description' => $offer->description,
                    'highlights' => $offer->highlights ?? [],
                    'terms' => $offer->terms,
                    'starts_at' => $offer->starts_at?->toISOString(),
                    'ends_at' => $offer->ends_at?->toISOString(),
                ])
                ->values()
                ->all(), []),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
