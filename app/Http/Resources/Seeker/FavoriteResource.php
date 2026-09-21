<?php

namespace App\Http\Resources\Seeker;

use App\Models\Organization;
use App\Models\PropertyListing;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $favoritable = $this->favoritable;

        if ($favoritable instanceof PropertyListing) {
            $favoritable->loadMissing(['organization.organizationType', 'media']);
        }

        if ($favoritable instanceof Organization) {
            $favoritable->loadMissing('organizationType');
        }

        if ($favoritable instanceof Service) {
            $favoritable->loadMissing(['organization', 'media']);
        }

        return [
            'id' => $this->id,
            'type' => $favoritable instanceof PropertyListing ? 'property' : ($favoritable instanceof Organization ? 'organization' : ($favoritable instanceof Service ? 'service' : null)),
            'target' => $favoritable instanceof PropertyListing
                ? PropertyListingResource::make($favoritable)->resolve($request)
                : ($favoritable instanceof Organization
                    ? OrganizationResource::make($favoritable)->resolve($request)
                    : ($favoritable instanceof Service ? ServiceResource::make($favoritable)->resolve($request) : null)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
