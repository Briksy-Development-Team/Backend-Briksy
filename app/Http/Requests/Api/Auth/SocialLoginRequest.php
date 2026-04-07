<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'access_token' => ['required', 'string'],
            'role' => ['nullable', 'string', Rule::in(['seeker', 'admin', 'admin_staff'])],
        ];
    }
}
