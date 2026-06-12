<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Admin\AdminStaffResource;
use App\Models\Role;
use App\Models\User;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffController extends Controller
{
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
        ]);

        $staff = DB::transaction(function () use ($validated, $organizationId): User {
            $staff = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password_hash' => Str::password(16),
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

            return $staff->load('roles');
        });

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
        ]);

        $user->fill($validated);
        $user->organization_id = $organizationId;
        $user->save();

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
}
