<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\StaffIndexRequest;
use App\Http\Requests\Api\SuperAdmin\StaffStoreRequest;
use App\Http\Requests\Api\SuperAdmin\StaffUpdateRequest;
use App\Http\Resources\SuperAdmin\UserResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    public function index(StaffIndexRequest $request): JsonResponse
    {
        $query = User::query()
            ->whereHas('roles', fn ($role) => $role->where('name', 'admin_staff'))
            ->with(['roles', 'organization']);

        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());
        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $staffMembers = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            UserResource::collection($staffMembers),
            $staffMembers,
            'Staff members retrieved successfully.'
        );
    }

    public function show(User $staff): JsonResponse
    {
        if (!$staff->hasRole('admin_staff')) {
            return response()->json([
                'success' => false,
                'message' => 'Staff member not found.',
            ], 404);
        }

        $staff->load(['roles', 'organization']);

        return $this->success(
            new UserResource($staff),
            'Staff member retrieved successfully.'
        );
    }

    public function store(StaffStoreRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password_hash' => $request->input('password'),
                'mobile_number' => $request->input('mobile_number'),
                'display_name' => $request->input('display_name'),
                'organization_id' => $request->input('organization_id'),
                'id_verified' => false,
            ]);

            $role = Role::query()->firstOrCreate(
                ['name' => 'admin_staff'],
                ['scope' => 'tenant', 'is_system' => true]
            );

            $user->roles()->syncWithoutDetaching([
                $role->id => [
                    'id' => (string) str()->uuid(),
                    'organization_id' => $request->input('organization_id'),
                ],
            ]);

            $this->syncDirectPermissions($user, $request->input('permissions', []));

            return $user->load(['roles', 'organization']);
        });

        return $this->created(
            new UserResource($user),
            'Staff member created successfully.'
        );
    }

    public function update(StaffUpdateRequest $request, User $staff): JsonResponse
    {
        if (!$staff->hasRole('admin_staff')) {
            return response()->json([
                'success' => false,
                'message' => 'Staff member not found.',
            ], 404);
        }

        $validatedData = $request->validated();

        if (!empty($validatedData['password'])) {
            $validatedData['password_hash'] = $validatedData['password'];
        }

        unset($validatedData['password']);
        $permissionNames = array_key_exists('permissions', $validatedData)
            ? $validatedData['permissions']
            : null;
        unset($validatedData['permissions']);

        $staff->fill($validatedData);
        $staff->save();

        if (array_key_exists('organization_id', $validatedData) && $validatedData['organization_id']) {
            $role = Role::query()->firstOrCreate(
                ['name' => 'admin_staff'],
                ['scope' => 'tenant', 'is_system' => true]
            );

            if ($staff->roles()->where('roles.id', $role->id)->exists()) {
                $staff->roles()->updateExistingPivot($role->id, [
                    'organization_id' => $validatedData['organization_id'],
                ]);
            } else {
                $staff->roles()->attach($role->id, [
                    'id' => (string) str()->uuid(),
                    'organization_id' => $validatedData['organization_id'],
                ]);
            }
        }

        if ($permissionNames !== null) {
            $this->syncDirectPermissions($staff, $permissionNames);
        }

        return $this->success(
            new UserResource($staff->fresh()->load(['roles', 'organization'])),
            'Staff member updated successfully.'
        );
    }

    private function syncDirectPermissions(User $user, array $permissionNames): void
    {
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
}
