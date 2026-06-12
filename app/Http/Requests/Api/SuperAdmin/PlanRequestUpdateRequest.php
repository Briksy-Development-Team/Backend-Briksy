<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequestUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['sometimes', 'nullable', 'uuid', 'exists:organizations,id'],
            'requested_by' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'plan_id' => ['sometimes', 'nullable', 'uuid', 'exists:subscription_plans,id'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'requested_plan_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_cycle' => ['sometimes', 'nullable', 'string', 'max:20'],
            'message' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            'admin_notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
