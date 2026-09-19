<?php

namespace App\Http\Resources\Seeker;

use App\Services\PublicMediaEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $entitlements = app(PublicMediaEntitlementService::class);
        $media = $this->whenLoaded('media', fn () => $this->media);
        $images = $media instanceof \Illuminate\Support\Collection ? $media->where('media_type', 'image')->values() : collect();
        $videos = $media instanceof \Illuminate\Support\Collection ? $media->where('media_type', 'video')->values() : collect();

        $images = $entitlements->take($images, $entitlements->limitFor($this->organization, 'service', 'image'));
        $videos = $entitlements->take($videos, $entitlements->limitFor($this->organization, 'service', 'video'));

        return [
            'id' => $this->id,
            'generated_id' => $this->generated_id,
            'display_id' => $this->display_id,
            'name' => $this->name,
            'title' => $this->title,
            'category' => $this->category,
            'slug' => $this->slug,
            'description' => $this->description,
            'service_area' => $this->service_area,
            'service_area_geometry' => $this->service_area_geometry,
            'rate_from' => $this->rate_from !== null ? (float) $this->rate_from : null,
            'rate_to' => $this->rate_to !== null ? (float) $this->rate_to : null,
            'organization' => $this->whenLoaded('organization', fn (): ?array => $this->organization ? [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'slug' => $this->organization->slug,
                'is_verified' => (bool) $this->organization->is_verified,
                'contact_email' => $this->organization->contact_email,
                'contact_phone' => $this->organization->contact_phone,
                'address' => $this->organization->address,
                'state' => $this->organization->state,
                'postcode' => $this->organization->postcode,
                'logo_url' => $this->organizationMediaUrl($request, $this->organization->logo_url, $this->organization->id, 'profile'),
                'banner_url' => $this->organizationMediaUrl($request, $this->organization->banner_url, $this->organization->id, 'banner'),
            ] : null),
            'images' => $this->mediaPayload($request, $images),
            'videos' => $this->mediaPayload($request, $videos),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function mediaPayload(Request $request, \Illuminate\Support\Collection $media): array
    {
        return $media->map(fn ($item): array => [
            'id' => $item->id,
            'url' => $this->mediaUrl($request, $item->file_url, $item->id),
            'is_primary' => (bool) $item->is_primary,
            'sort_order' => (int) $item->sort_order,
        ])->values()->all();
    }

    private function mediaUrl(Request $request, string $url, string $id): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').'/api/service-media/'.$id;
    }

    private function organizationMediaUrl(Request $request, ?string $url, ?string $organizationId, string $type): ?string
    {
        if (!$url || !$organizationId) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return rtrim($request->getSchemeAndHttpHost(), '/').$url;
        }

        $version = $this->organization?->updated_at?->timestamp;

        return rtrim($request->getSchemeAndHttpHost(), '/').'/api/organization-media/'.$organizationId.'/'.$type.'?v='.rawurlencode((string) ($version ?? 0));
    }
}
