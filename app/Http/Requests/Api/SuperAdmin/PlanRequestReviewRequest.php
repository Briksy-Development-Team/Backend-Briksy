<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class PlanRequestReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'admin_notes' => ['nullable', 'string'],
            'create_order' => ['sometimes', 'boolean'],
            'plan_id' => ['sometimes', 'nullable', 'uuid', 'exists:subscription_plans,id'],
            'organization_id' => ['sometimes', 'nullable', 'uuid', 'exists:organizations,id'],
        ];
    }
}
