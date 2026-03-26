<?php

namespace App\Http\Requests\Api\Seeker;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['property', 'organization'])],
            'target_id' => ['required', 'uuid'],
        ];
    }
}
