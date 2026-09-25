<?php

namespace App\Http\Resources\Seeker;

use App\Models\Organization;
use App\Models\PropertyListing;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $target = $this->collectable;

        return [
            'id' => $this->id,
            'type' => $target instanceof PropertyListing ? 'property' : ($target instanceof Organization ? 'organization' : ($target instanceof Service ? 'service' : null)),
            'target' => $target instanceof PropertyListing
                ? PropertyListingResource::make($target)->resolve($request)
                : ($target instanceof Organization
                    ? OrganizationResource::make($target)->resolve($request)
                    : ($target instanceof Service ? ServiceResource::make($target)->resolve($request) : null)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
