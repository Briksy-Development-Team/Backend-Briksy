<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('coupon')?->id ?? $this->route('coupon') ?? null;
        $codeRule = $this->isMethod('post')
            ? ['required', 'string', 'max:50', 'unique:coupons,code']
            : ['sometimes', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($ignoreId)];

        return [
            'code' => $codeRule,
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'discount_type' => [$this->isMethod('post') ? 'required' : 'sometimes', Rule::in(['fixed', 'percentage'])],
            'discount_value' => [$this->isMethod('post') ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_count' => ['nullable', 'integer', 'min:0'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'expired'])],
        ];
    }
}
