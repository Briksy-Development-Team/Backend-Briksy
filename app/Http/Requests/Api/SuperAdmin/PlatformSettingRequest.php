<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.key' => ['required', 'string', 'max:255'],
            'settings.*.value' => ['nullable'],
            'settings.*.type' => ['required', Rule::in(['string', 'number', 'boolean', 'json'])],
            'settings.*.group' => ['nullable', 'string', 'max:255'],
            'settings.*.label' => ['nullable', 'string', 'max:255'],
            'settings.*.is_public' => ['nullable', 'boolean'],
        ];
    }
}
