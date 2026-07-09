<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyAbnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'abn' => ['required', 'string'],
            'business_type' => ['nullable', Rule::in(['organisation', 'company', 'solo_trader'])],
        ];
    }
}
