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
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['nullable', 'integer', 'min:0'],
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'yearly_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'billing_enabled' => ['sometimes', 'boolean'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'propertyLimit' => ['required', 'integer', 'min:0'],
            'popular' => ['required', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'features' => ['required', 'array'],
            'features.*.name' => ['required', 'string', 'max:100'],
            'features.*.enabled' => ['required', 'boolean'],
            'features.*.value' => ['nullable', 'numeric', 'min:0'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
