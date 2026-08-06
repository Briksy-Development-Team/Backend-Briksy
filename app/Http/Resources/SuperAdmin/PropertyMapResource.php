<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyMapResource extends JsonResource
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
        $images = $this->whenLoaded('media', function () use ($request): array {
            return $this->media
                ->where('media_type', 'image')
                ->sortBy('sort_order')
                ->take(4)
                ->map(fn ($media): array => [
                    'id' => $media->id,
                    'url' => $this->normalizeMediaUrl($request, $media->file_url, (string) $media->id),
                    'is_primary' => (bool) $media->is_primary,
                    'sort_order' => (int) $media->sort_order,
                ])
                ->values()
                ->all();
        }, []);

        $videos = $this->whenLoaded('media', function () use ($request): array {
            return $this->media
                ->where('media_type', 'video')
                ->sortBy('sort_order')
                ->take(2)
                ->map(fn ($media): array => [
                    'id' => $media->id,
                    'url' => $this->normalizeMediaUrl($request, $media->file_url, (string) $media->id),
                    'is_primary' => (bool) $media->is_primary,
                    'sort_order' => (int) $media->sort_order,
                ])
                ->values()
                ->all();
        }, []);

        $primaryImage = collect($images)->firstWhere('is_primary', true) ?? $images[0] ?? null;

        return [
            'id' => $this->id,
            'property_number' => $this->generated_id ?: $this->id,
            'title' => $this->title,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'status' => $this->status,
            'verified' => (bool) $this->location_verified,
            'organization_name' => $this->whenLoaded('organization', fn (): ?string => $this->organization?->name),
            'property_type' => $this->whenLoaded('propertyType', fn (): ?string => $this->propertyType?->name),
            'city' => $this->suburb,
            'state' => $this->state,
            'country' => $this->country,
            'image_url' => $primaryImage['url'] ?? null,
            'images' => $images,
            'videos' => $videos,
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
            'address' => $this->formatted_address ?? $this->full_address ?? $this->address ?? $this->address_line_1,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
