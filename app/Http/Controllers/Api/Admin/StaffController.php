<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Admin\AdminStaffResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
<<<<<<< Updated upstream
=======
use App\Services\NotificationService;
>>>>>>> Stashed changes
use App\Support\Business\BusinessModuleResolver;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
<<<<<<< Updated upstream
    public function __construct(private readonly BusinessModuleResolver $moduleResolver)
=======
    public function __construct(
        private readonly BusinessModuleResolver $moduleResolver,
        private readonly NotificationService $notificationService
    )
>>>>>>> Stashed changes
    {
    }

    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'Admin account is not assigned to an organization.',
            ], 403);
        }

        $query = User::query()
            ->with('roles')
            ->where('organization_id', $organizationId)
            ->whereHas('roles', static function ($roleQuery): void {
                $roleQuery->whereIn('roles.name', ['admin', 'admin_staff']);
            });

        ApiQueryBuilder::applySearch($query, $request->string('search')->toString(), ['name', 'email']);
        $staff = $query->orderByDesc('created_at')->paginate(ApiQueryBuilder::normalizePerPage($request->integer('items_per_page'), 10, 100));

        return $this->paginated(
            AdminStaffResource::collection($staff)->resolve(),
            $staff,
            'Staff members retrieved successfully.'
        );
    }

    public function show(Request $request, User $user): JsonResponse
    {
        abort_unless($this->isInAdminOrganization($request, $user), 403);
        abort_unless($user->hasAnyRole(['admin', 'admin_staff']), 404);

        return $this->success(
            new AdminStaffResource($user->loadMissing('roles')),
            'Staff member retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'Admin account is not assigned to an organization.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'mobile_number' => ['nullable', 'string', 'max:30', 'unique:users,mobile_number'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $staff = DB::transaction(function () use ($validated, $organizationId): User {
            $staff = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password_hash' => $validated['password'],
                'mobile_number' => $validated['mobile_number'] ?? null,
                'display_name' => $validated['display_name'] ?? null,
                'organization_id' => $organizationId,
                'id_verified' => false,
            ]);

            $role = Role::query()->firstOrCreate(
                ['name' => 'admin_staff'],
                ['scope' => 'tenant', 'is_system' => true]
            );

            $staff->roles()->syncWithoutDetaching([
                $role->id => [
                    'id' => (string) str()->uuid(),
                    'organization_id' => $organizationId,
                ],
            ]);

            $this->syncDirectPermissions($staff, $validated['permissions'] ?? []);

            return $staff->load('roles');
        });

        $this->notificationService->notifyAdminsForOrganisation(
            $organizationId,
            $this->notificationService->buildPayload(
                'user_invited',
                'User invited',
                sprintf('Staff member "%s" has been invited.', $staff->name),
                User::class,
                $staff->id,
                '/admin/users',
                'normal',
                $request->user()?->id,
                $organizationId
            ),
            'User invited',
            'View users'
        );

        return $this->created(
            new AdminStaffResource($staff),
            'Staff member created successfully.'
        );
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($this->isInAdminOrganization($request, $user), 403);
        abort_unless($user->hasAnyRole(['admin', 'admin_staff']), 404);

        $organizationId = $request->user()?->organization_id;

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:150', 'unique:users,email,' . $user->id],
            'mobile_number' => ['nullable', 'string', 'max:30', 'unique:users,mobile_number,' . $user->id],
            'display_name' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'min:8'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if (!empty($validated['password'])) {
            $validated['password_hash'] = $validated['password'];
        }

        $permissionNames = array_key_exists('permissions', $validated)
            ? $validated['permissions']
            : null;

        unset($validated['password'], $validated['permissions']);

        $user->fill($validated);
        $user->organization_id = $organizationId;
        $user->save();

        if ($permissionNames !== null) {
            $this->syncDirectPermissions($user, $permissionNames);
        }

<<<<<<< Updated upstream
=======
        $this->notificationService->notifyAdminsForOrganisation(
            $organizationId,
            $this->notificationService->buildPayload(
                'user_role_changed',
                'User updated',
                sprintf('Staff member "%s" was updated.', $user->name),
                User::class,
                $user->id,
                '/admin/users',
                'normal',
                $request->user()?->id,
                $organizationId
            ),
            'User updated',
            'View users'
        );

>>>>>>> Stashed changes
        return $this->success(
            new AdminStaffResource($user->fresh('roles')),
            'Staff member updated successfully.'
        );
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($this->isInAdminOrganization($request, $user), 403);
        abort_unless($user->hasAnyRole(['admin', 'admin_staff']), 404);

        $user->delete();

        return $this->success([], 'Staff member deleted successfully.');
    }

    private function isInAdminOrganization(Request $request, User $user): bool
    {
        $organizationId = $request->user()?->organization_id;

        return (bool) $organizationId && $user->organization_id === $organizationId;
    }

    private function syncDirectPermissions(User $user, array $permissionNames): void
    {
        $this->assertAllowedPermissions($user, $permissionNames);

        $permissionIds = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $payload = [];

        foreach ($permissionIds as $permissionId) {
            $payload[$permissionId] = [
                'id' => (string) str()->uuid(),
                'effect' => 'allow',
            ];
        }

        $user->directPermissions()->sync($payload);
    }

    private function assertAllowedPermissions(User $user, array $permissionNames): void
    {
        $allowed = ['dashboard.view', 'user.view', 'user.create', 'user.update', 'user.delete', 'settings.view', 'settings.update'];

        if ($this->moduleResolver->isPropertyAllowed($user)) {
            $allowed = array_merge($allowed, ['property.view', 'property.create', 'property.update', 'property.delete']);
        }

        if ($this->moduleResolver->isServiceAllowed($user)) {
            $allowed = array_merge($allowed, ['service.view', 'service.create', 'service.update', 'service.delete']);
        }

        $invalid = array_values(array_diff($permissionNames, $allowed));

        if ($invalid !== []) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'One or more permissions are not allowed for this business type.',
                'errors' => [
                    'permissions' => ['Invalid permissions: ' . implode(', ', $invalid)],
                ],
            ], 403));
        }
    }
}
