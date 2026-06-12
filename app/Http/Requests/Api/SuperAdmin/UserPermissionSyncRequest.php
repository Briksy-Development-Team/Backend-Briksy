<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserPermissionSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('permission.manage');
    }

    public function rules(): array
    {
        return [
            'overrides' => ['required', 'array'],
            'overrides.*.permission_id' => ['required', 'uuid', Rule::exists('permissions', 'id')],
            'overrides.*.effect' => ['required', Rule::in(['allow', 'deny'])],
        ];
    }

    public function targetUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }
}
