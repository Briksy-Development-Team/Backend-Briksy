<?php

namespace App\Http\Resources\Seeker;

use App\Models\Organization;
use App\Models\PropertyListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $favoritable = $this->favoritable;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $favoritable instanceof PropertyListing ? 'property' : ($favoritable instanceof Organization ? 'organization' : null),
            'target' => match (true) {
                $favoritable instanceof PropertyListing => [
                    'id' => $favoritable->id,
                    'title' => $favoritable->title,
                    'suburb' => $favoritable->suburb,
                    'postcode' => $favoritable->postcode,
                ],
                $favoritable instanceof Organization => [
                    'id' => $favoritable->id,
                    'name' => $favoritable->name,
                    'slug' => $favoritable->slug,
                    'is_verified' => (bool) $favoritable->is_verified,
                ],
                default => null,
            },
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
