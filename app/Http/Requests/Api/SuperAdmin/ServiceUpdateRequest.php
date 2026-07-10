<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $service = $this->route('service');

        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'slug' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('services', 'slug')->ignore($service?->id),
            ],
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
