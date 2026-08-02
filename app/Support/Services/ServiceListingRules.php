<?php

namespace App\Support\Services;

use Illuminate\Validation\Rule;

final class ServiceListingRules
{
    public static function store(?string $ignoreServiceId = null): array
    {
        $slugRule = ['required', 'string', 'max:100'];

        if ($ignoreServiceId) {
            $slugRule[] = Rule::unique('services', 'slug')->ignore($ignoreServiceId);
        } else {
            $slugRule[] = 'unique:services,slug';
        }

        return [
            'name' => ['required', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'slug' => $slugRule,
            'description' => ['nullable', 'string'],
            'service_area' => ['nullable', 'string', 'max:255'],
            'service_area_geometry' => ['nullable', 'array'],
            'service_area_geometry.type' => ['nullable', 'string', 'in:Polygon'],
            'service_area_geometry.coordinates' => ['nullable', 'array'],
            'rate_from' => ['nullable', 'numeric', 'min:0'],
            'rate_to' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'type_id' => ['nullable', 'uuid', 'exists:organization_types,id'],
        ];
    }
}
