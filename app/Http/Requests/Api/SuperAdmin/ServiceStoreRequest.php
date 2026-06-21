<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'unique:services,slug'],
            'description' => ['nullable', 'string'],
            'service_area' => ['nullable', 'string', 'max:255'],
            'rate_from' => ['nullable', 'numeric', 'min:0'],
            'rate_to' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'type_id' => ['nullable', 'uuid', 'exists:organization_types,id'],
        ];
    }
}
