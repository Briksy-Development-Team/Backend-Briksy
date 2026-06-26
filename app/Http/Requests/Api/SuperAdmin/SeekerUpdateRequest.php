<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SeekerUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $seeker = $this->route('seeker');

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:150', Rule::unique('users', 'email')->ignore($seeker?->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'mobile_number' => ['nullable', 'string', 'max:30', Rule::unique('users', 'mobile_number')->ignore($seeker?->id)],
            'display_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
