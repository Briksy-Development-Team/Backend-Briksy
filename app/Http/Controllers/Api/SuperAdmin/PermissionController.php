<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\PermissionIndexRequest;
use App\Http\Requests\Api\SuperAdmin\RolePermissionSyncRequest;
use App\Http\Requests\Api\SuperAdmin\UserPermissionSyncRequest;
use App\Http\Resources\SuperAdmin\PermissionResource;
use App\Http\Resources\SuperAdmin\UserResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Business\BusinessModuleResolver;
use App\Models\UserPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionController extends Controller
{
    public function __construct(private readonly BusinessModuleResolver $moduleResolver)
    {
    }

    public function index(PermissionIndexRequest $request): JsonResponse
    {
        $query = Permission::query()->orderBy('module')->orderBy('action')->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            });
        }

        if ($request->filled('module')) {
            $query->where('module', $request->string('module')->toString());
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->toString());
        }

        $permissions = $query->get();

        return $this->success([
            'items' => PermissionResource::collection($permissions)->resolve(),
            'grouped' => $this->groupPermissions($permissions),
        ], 'Permissions retrieved successfully.');
    }

    public function roles(): JsonResponse
    {
        $roles = Role::query()
            ->withCount('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'scope' => $role->scope,
                'is_system' => (bool) $role->is_system,
                'permissions_count' => (int) $role->permissions_count,
            ])
            ->values()
            ->all();

        return $this->success($roles, 'Roles retrieved successfully.');
    }

    public function rolePermissions(Role $role): JsonResponse
    {
        $role->load('permissions');
        $permissions = $role->permissions
            ->sortBy(fn (Permission $permission): string => sprintf('%s|%s|%s', $permission->module, $permission->action, $permission->name))
            ->values();

        return $this->success([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'scope' => $role->scope,
                'is_system' => (bool) $role->is_system,
            ],
            'permissions' => PermissionResource::collection($permissions)->resolve(),
            'grouped' => $this->groupPermissions($permissions),
        ], 'Role permissions retrieved successfully.');
    }

    public function updateRolePermissions(RolePermissionSyncRequest $request, Role $role): JsonResponse
    {
        $permissionIds = $request->validatedPermissionIds();

        if ($request->isCoreSuperAdminRole()) {
            $permissionIds = Permission::query()
                ->where('module', '!=', 'webhook')
                ->pluck('id')
                ->all();
        }

        $permissions = Permission::query()->whereIn('id', $permissionIds)->get();

        DB::transaction(function () use ($role, $permissionIds): void {
            DB::table('role_permissions')->where('role_id', $role->id)->delete();

            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->insert([
                    'id' => (string) Str::uuid(),
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return $this->success([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'scope' => $role->scope,
                'is_system' => (bool) $role->is_system,
            ],
            'permissions' => PermissionResource::collection($permissions->sortBy(fn (Permission $permission): string => sprintf('%s|%s|%s', $permission->module, $permission->action, $permission->name))->values())->resolve(),
            'grouped' => $this->groupPermissions($permissions),
        ], 'Role permissions updated successfully.');
    }

    public function users(Request $request): JsonResponse
    {
        $query = User::query()->with(['roles.permissions', 'directPermissions'])->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->limit(50)->get();

        return $this->success(UserResource::collection($users)->resolve(), 'Users retrieved successfully.');
    }

    public function userPermissions(User $user): JsonResponse
    {
        $user->load(['roles.permissions', 'directPermissions']);

        return $this->success($this->buildUserPermissionPayload($user), 'User permissions retrieved successfully.');
    }

    public function updateUserPermissions(UserPermissionSyncRequest $request, User $user): JsonResponse
    {
        $overrides = collect($request->validated()['overrides'] ?? []);

        DB::transaction(function () use ($user, $overrides): void {
            UserPermission::query()->where('user_id', $user->id)->delete();

            foreach ($overrides as $override) {
                UserPermission::query()->create([
                    'user_id' => $user->id,
                    'permission_id' => $override['permission_id'],
                    'effect' => $override['effect'],
                ]);
            }
        });

        $user->load(['roles.permissions', 'directPermissions']);

        return $this->success($this->buildUserPermissionPayload($user), 'User permissions updated successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing(['roles.permissions', 'directPermissions', 'organization.plan', 'organization.currentSubscription']);

        return $this->success($this->buildUserPermissionPayload($user), 'Current permissions retrieved successfully.');
    }

    protected function groupPermissions(iterable $permissions): array
    {
        return collect($permissions)
            ->groupBy('module')
            ->map(fn ($items, string $module): array => [
                'module' => $module,
                'permissions' => PermissionResource::collection(collect($items)->values())->resolve(),
            ])
            ->values()
            ->all();
    }

    protected function buildUserPermissionPayload(User $user): array
    {
        $directPermissions = $user->directPermissions->map(function (Permission $permission): array {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $permission->display_name,
                'module' => $permission->module,
                'action' => $permission->action,
                'effect' => $permission->pivot?->effect,
            ];
        })->values()->all();

        $rolePermissions = $user->roles
            ->flatMap(fn (Role $role): array => $role->permissions->map(fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $permission->display_name,
                'module' => $permission->module,
                'action' => $permission->action,
                'role_id' => $role->id,
                'role_name' => $role->name,
            ])->values()->all())
            ->unique('name')
            ->values()
            ->all();

        $effectivePermissions = $user->getAllPermissions();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'organization_id' => $user->organization_id,
                'business_type' => $this->moduleResolver->businessType($user),
                'business_verification_status' => $this->moduleResolver->verificationStatus($user),
                'roles' => $user->roles->pluck('name')->values()->all(),
                'subscription' => $user->subscriptionSummary(),
            ],
            'roles' => $user->roles->pluck('name')->values()->all(),
            'role_permissions' => $rolePermissions,
            'direct_permissions' => $directPermissions,
            'effective_permissions' => PermissionResource::collection($effectivePermissions)->resolve(),
            'effective_permission_names' => $effectivePermissions->pluck('name')->values()->all(),
            'enabled_modules' => $this->moduleResolver->resolve($user),
            'grouped' => $this->groupPermissions($effectivePermissions),
        ];
    }
}
