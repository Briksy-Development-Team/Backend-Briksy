<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PropertyImportPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'string'],
        ];
    }
}
