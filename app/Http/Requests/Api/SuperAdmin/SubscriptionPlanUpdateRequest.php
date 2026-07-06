<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionPlanUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $plan = $this->route('subscriptionPlan');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('subscription_plans', 'name')->ignore($plan?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'monthly_price' => ['sometimes', 'numeric', 'min:0'],
            'yearly_price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'billing_enabled' => ['sometimes', 'boolean'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'propertyLimit' => ['sometimes', 'integer', 'min:0'],
            'popular' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'features' => ['sometimes', 'array'],
            'features.*.name' => ['required_with:features', 'string', 'max:100'],
            'features.*.enabled' => ['required_with:features', 'boolean'],
            'features.*.value' => ['nullable', 'numeric', 'min:0'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
