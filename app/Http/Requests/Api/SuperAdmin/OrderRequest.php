<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_number' => ['sometimes', 'string', 'max:255'],
            'reference_no' => ['sometimes', 'string', 'max:255'],
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'plan_id' => ['nullable', 'uuid', 'exists:subscription_plans,id'],
            'coupon_id' => ['nullable', 'uuid', 'exists:coupons,id'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'plan_request_id' => ['nullable', 'uuid', 'exists:plan_requests,id'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'billing_cycle' => ['nullable', 'string', 'max:20'],
            'payment_status' => ['nullable', Rule::in(['pending', 'paid', 'failed', 'refunded', 'cancelled'])],
            'order_status' => ['nullable', Rule::in(['draft', 'confirmed', 'active', 'expired', 'cancelled'])],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
