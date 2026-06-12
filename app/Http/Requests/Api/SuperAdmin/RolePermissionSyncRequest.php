<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RolePermissionSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('permission.manage');
    }

    public function rules(): array
    {
        return [
            'permission_ids' => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['required', 'uuid', Rule::exists('permissions', 'id')],
        ];
    }

    public function role(): ?Role
    {
        $role = $this->route('role');

        return $role instanceof Role ? $role : null;
    }

    public function validatedPermissionIds(): array
    {
        return collect($this->validated()['permission_ids'] ?? [])->unique()->values()->all();
    }

    public function isCoreSuperAdminRole(): bool
    {
        return $this->role()?->name === 'super_admin';
    }
}
