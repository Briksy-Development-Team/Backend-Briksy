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
            'plan_family' => ['sometimes', 'string', 'in:property_owner,trades_professional,buyers_agent,builders'],
            'name' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('subscription_plans', 'name')
                    ->where(fn ($query) => $query->where('plan_family', $this->input('plan_family', $plan?->plan_family)))
                    ->ignore($plan?->id),
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
            'addon_ids' => ['sometimes', 'array'],
            'addon_ids.*' => ['string', 'uuid', 'exists:addons,id'],
        ];
    }
}
