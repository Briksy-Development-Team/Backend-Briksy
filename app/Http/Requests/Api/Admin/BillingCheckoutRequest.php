<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'uuid', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['uuid', 'exists:addons,id'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['integer', 'min:1'],
        ];
    }
}
