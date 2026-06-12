<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class CouponValidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
        ];
    }
}
