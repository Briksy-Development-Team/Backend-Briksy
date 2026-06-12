<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'requested_by' => ['nullable', 'uuid', 'exists:users,id'],
            'plan_id' => ['nullable', 'uuid', 'exists:subscription_plans,id'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'requested_plan_name' => ['nullable', 'string', 'max:255'],
            'billing_cycle' => ['nullable', 'string', 'max:20'],
            'message' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            'admin_notes' => ['nullable', 'string'],
        ];
    }
}
