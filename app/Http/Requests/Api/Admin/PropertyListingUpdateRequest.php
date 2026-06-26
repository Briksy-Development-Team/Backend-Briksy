<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyListingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'property_type_id' => ['sometimes', 'nullable', 'uuid', 'exists:property_types,id'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:500'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:500'],
            'address' => ['nullable', 'string', 'max:500'],
            'full_address' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['Draft', 'Published', 'Archived'])],
            'suburb' => ['nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:50'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric'],
            'formatted_address' => ['sometimes', 'nullable', 'string'],
            'place_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'numeric'],
            'location_verified' => ['sometimes', 'boolean'],
            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'image', 'max:5120'],
            'videos' => ['nullable', 'array'],
            'videos.*' => ['file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska', 'max:51200'],
        ];
    }
}
