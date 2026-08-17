<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class PermissionInheritanceService
{
    /**
     * Copy the current role defaults to a newly-created user's direct grants.
     * Existing users are intentionally never touched by this method.
     */
    public function applyDefaults(User $user): void
    {
        $permissionIds = $this->roleDefaultPermissionIds($user);

        if ($permissionIds === []) {
            return;
        }

        $payload = [];
        foreach ($permissionIds as $permissionId) {
            $payload[$permissionId] = [
                'id' => (string) str()->uuid(),
                'effect' => 'allow',
            ];
        }

        $user->directPermissions()->sync($payload);
    }

    /**
     * Persist the permissions selected in a staff form.
     *
     * Role defaults omitted from the selection become explicit denies so the
     * user's override remains effective even though the role still has that
     * default. This preserves the existing allow/deny RBAC model.
     */
    public function syncSelection(User $user, array $permissionNames): void
    {
        $selected = Permission::query()
            ->whereIn('name', array_values(array_unique($permissionNames)))
            ->get(['id', 'name']);

        $selectedNames = $selected->pluck('name')->all();
        $defaultPermissions = $this->roleDefaultPermissions($user);
        $payload = [];

        foreach ($selected as $permission) {
            $payload[$permission->id] = [
                'id' => (string) str()->uuid(),
                'effect' => 'allow',
            ];
        }

        foreach ($defaultPermissions as $permission) {
            if (!in_array($permission->name, $selectedNames, true)) {
                $payload[$permission->id] = [
                    'id' => (string) str()->uuid(),
                    'effect' => 'deny',
                ];
            }
        }

        $user->directPermissions()->sync($payload);
    }

    /** @return array<int, string> */
    public function roleDefaultPermissionIds(User $user): array
    {
        return $this->roleDefaultPermissions($user)->pluck('id')->values()->all();
    }

    /** @return Collection<int, Permission> */
    public function roleDefaultPermissions(User $user): Collection
    {
        $user->loadMissing('roles.permissions');

        return $user->roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->unique('id')
            ->values();
    }
}
