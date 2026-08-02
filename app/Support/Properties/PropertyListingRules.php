<?php

namespace App\Support\Properties;

use Illuminate\Validation\Rule;

final class PropertyListingRules
{
    public static function store(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'property_type_id' => ['nullable', 'uuid', 'exists:property_types,id'],
            'address_line_1' => ['nullable', 'string', 'max:500'],
            'address_line_2' => ['nullable', 'string', 'max:500'],
            'address' => ['nullable', 'string', 'max:500'],
            'full_address' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(PropertyWorkflow::STATUSES)],
            'suburb' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:50'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric'],
            'formatted_address' => ['nullable', 'string'],
            'place_id' => ['nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'numeric'],
            'location_verified' => ['prohibited'],
            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'image', 'max:5120'],
            'videos' => ['nullable', 'array'],
            'videos.*' => ['file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska', 'max:51200'],
        ];
    }

    public static function update(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'property_type_id' => ['sometimes', 'nullable', 'uuid', 'exists:property_types,id'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:500'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:500'],
            'address' => ['nullable', 'string', 'max:500'],
            'full_address' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(PropertyWorkflow::STATUSES)],
            'suburb' => ['nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:50'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric'],
            'formatted_address' => ['sometimes', 'nullable', 'string'],
            'place_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'numeric'],
            'location_verified' => ['prohibited'],
            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'image', 'max:5120'],
            'videos' => ['nullable', 'array'],
            'videos.*' => ['file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska', 'max:51200'],
        ];
    }
}
