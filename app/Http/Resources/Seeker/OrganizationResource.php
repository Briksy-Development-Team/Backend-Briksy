<?php

namespace App\Http\Resources\Seeker;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    private function mediaUrl(Request $request, ?string $url, string $type): ?string
    {
        if (!$url) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return rtrim($request->getSchemeAndHttpHost(), '/').$url;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').'/api/organization-media/'.$this->id.'/'.$type;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'generated_id' => $this->generated_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->mediaUrl($request, $this->logo_url, 'profile'),
            'banner_url' => $this->mediaUrl($request, $this->banner_url, 'banner'),
            'abn' => $this->abn,
            'rating' => (float) $this->avg_org_rating,
            'ranking_priority' => $this->ranking_priority,
            'is_verified' => (bool) $this->is_verified,
            'address' => $this->address,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'contact' => [
                'email' => $this->contact_email,
                'phone' => $this->contact_phone,
            ],
            'type' => $this->whenLoaded('organizationType', fn (): ?array => $this->organizationType ? [
                'id' => $this->organizationType->id,
                'name' => $this->organizationType->name,
                'slug' => $this->organizationType->slug,
            ] : null),
            'services' => $this->whenLoaded('services', fn (): array => $this->services
                ->map(fn ($service): array => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'slug' => $service->slug,
                    'description' => $service->pivot?->description,
                    'starting_price' => $service->pivot?->starting_price !== null ? (float) $service->pivot->starting_price : null,
                ])->values()->all()),
            'service_groups' => $this->whenLoaded('serviceGroups', fn (): array => $this->serviceGroups
                ->map(fn ($group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'slug' => $group->slug,
                    'package_price' => $group->pivot?->package_price !== null ? (float) $group->pivot->package_price : null,
                ])->values()->all()),
        ];
    }
}
