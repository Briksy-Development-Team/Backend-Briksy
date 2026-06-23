<?php

namespace App\Support\Maps;

use App\Models\PropertyListing;

class LocationPayload
{
    public static function fromPropertyListing(PropertyListing $propertyListing): array
    {
        return [
            'id' => $propertyListing->id,
            'title' => $propertyListing->title,
            'address' => $propertyListing->address_line_1 ?? $propertyListing->address,
            'address_line_1' => $propertyListing->address_line_1 ?? $propertyListing->address,
            'address_line_2' => $propertyListing->address_line_2,
            'formatted_address' => $propertyListing->formatted_address ?? $propertyListing->full_address,
            'place_id' => $propertyListing->place_id,
            'suburb' => $propertyListing->suburb,
            'state' => $propertyListing->state,
            'postcode' => $propertyListing->postcode,
            'country' => $propertyListing->country,
            'latitude' => $propertyListing->latitude !== null ? (float) $propertyListing->latitude : null,
            'longitude' => $propertyListing->longitude !== null ? (float) $propertyListing->longitude : null,
            'status' => $propertyListing->status,
            'organisation_id' => $propertyListing->org_id,
            'property_type' => $propertyListing->relationLoaded('propertyType') ? [
                'id' => $propertyListing->propertyType?->id,
                'name' => $propertyListing->propertyType?->name,
                'slug' => $propertyListing->propertyType?->slug,
            ] : null,
            'location_verified' => (bool) $propertyListing->location_verified,
        ];
    }
}
