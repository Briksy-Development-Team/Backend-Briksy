<?php

namespace App\Http\Requests\Api\Seeker;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSeekerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_postcode' => ['nullable', 'string', 'max:10'],
            'preferred_budget_min' => ['nullable', 'numeric', 'min:0'],
            'preferred_budget_max' => ['nullable', 'numeric', 'min:0', 'gte:preferred_budget_min'],
        ];
    }
}
