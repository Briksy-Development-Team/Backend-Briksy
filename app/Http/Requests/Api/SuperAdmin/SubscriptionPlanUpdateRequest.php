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
            'price' => ['sometimes', 'integer', 'min:0'],
            'propertyLimit' => ['sometimes', 'integer', 'min:0'],
            'popular' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'features' => ['sometimes', 'array'],
            'features.*.name' => ['required_with:features', 'string', 'max:100'],
            'features.*.enabled' => ['required_with:features', 'boolean'],
        ];
    }
}
