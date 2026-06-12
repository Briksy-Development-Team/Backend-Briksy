<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionPlanStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'unique:subscription_plans,name'],
            'price' => ['required', 'integer', 'min:0'],
            'propertyLimit' => ['required', 'integer', 'min:0'],
            'popular' => ['required', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'features' => ['required', 'array'],
            'features.*.name' => ['required', 'string', 'max:100'],
            'features.*.enabled' => ['required', 'boolean'],
        ];
    }
}
