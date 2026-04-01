<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\RegisterSeekerRequest;
use App\Http\Resources\Seeker\SeekerAccountResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegistrationController extends Controller
{
    public function store(RegisterSeekerRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password_hash' => $request->input('password'),
                'organization_id' => null,
                'id_verified' => false,
            ]);

            $seekerRole = Role::query()->firstOrCreate(
                ['name' => 'seeker'],
                ['scope' => 'global', 'is_system' => true]
            );

            $user->roles()->syncWithoutDetaching([
                $seekerRole->id => [
                    'id' => (string) str()->uuid(),
                    'organization_id' => null,
                ],
            ]);

            return $user->load('roles');
        });

        return $this->created(
            new SeekerAccountResource($user),
            'Seeker registered successfully.'
        );
    }

    public function loginSeeker(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->with('roles')
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $token = $user->createToken('seeker-auth', ['seeker'])->plainTextToken;

        return $this->success([
            'user' => new SeekerAccountResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
            'abilities' => ['seeker'],
        ], 'Login successful.');
    }

    public function registerAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $adminUser = DB::transaction(function () use ($validated): User {
            $adminUser = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password_hash' => $validated['password'],
                'organization_id' => null,
                'id_verified' => false,
            ]);

            $adminRole = Role::query()->firstOrCreate(
                ['name' => 'admin'],
                ['scope' => 'global', 'is_system' => true]
            );

            $adminUser->roles()->syncWithoutDetaching([
                $adminRole->id => [
                    'id' => (string) str()->uuid(),
                    'organization_id' => null,
                ],
            ]);

            return $adminUser->load('roles');
        });

        $token = $adminUser->createToken('admin-auth', ['admin'])->plainTextToken;

        return $this->created([
            'user' => new SeekerAccountResource($adminUser),
            'token' => $token,
            'token_type' => 'Bearer',
            'abilities' => ['admin'],
        ], 'Admin registered successfully.');
    }

    public function loginAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->with('roles')
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $abilities = $this->resolveAdminAbilities($user);

        if ($abilities === []) {
            return response()->json([
                'success' => false,
                'message' => 'This account is not authorized for admin APIs.',
            ], 403);
        }

        $token = $user->createToken('admin-auth', $abilities)->plainTextToken;

        return $this->success([
            'user' => new SeekerAccountResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
            'abilities' => $abilities,
        ], 'Login successful.');
    }

    public function registerAdminStaff(Request $request): JsonResponse
    {
        $authUser = auth('sanctum')->user();

        if (!$authUser || !$authUser->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin users can register admin staff.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
        ]);

        $organizationId = $validated['organization_id'] ?? $authUser->organization_id;

        $staffUser = DB::transaction(function () use ($validated, $organizationId): User {
            $staffUser = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password_hash' => $validated['password'],
                'organization_id' => $organizationId,
                'id_verified' => false,
            ]);

            $staffRole = Role::query()->firstOrCreate(
                ['name' => 'admin_staff'],
                ['scope' => 'tenant', 'is_system' => true]
            );

            $staffUser->roles()->syncWithoutDetaching([
                $staffRole->id => [
                    'id' => (string) str()->uuid(),
                    'organization_id' => $organizationId,
                ],
            ]);

            return $staffUser->load('roles');
        });

        $token = $staffUser->createToken('admin-staff-auth', ['admin_staff'])->plainTextToken;

        return $this->created([
            'user' => new SeekerAccountResource($staffUser),
            'token' => $token,
            'token_type' => 'Bearer',
            'abilities' => ['admin_staff'],
        ], 'Admin staff registered successfully.');
    }


    public function registerSuperAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $superRole = Role::query()->firstOrCreate(
            ['name' => 'super_admin'],
            ['scope' => 'global', 'is_system' => true]
        );

        $superAdminExists = $superRole->users()->exists();

        if ($superAdminExists) {
            $authUser = auth('sanctum')->user();

            if (!$authUser || !$authUser->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only super admin users can register another super admin.',
                ], 403);
            }
        }

        $superAdmin = DB::transaction(function () use ($validated, $superRole): User {
            $superAdmin = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password_hash' => $validated['password'],
                'organization_id' => null,
                'id_verified' => false,
            ]);

            $superAdmin->roles()->syncWithoutDetaching([
                $superRole->id => [
                    'id' => (string) str()->uuid(),
                    'organization_id' => null,
                ],
            ]);

            return $superAdmin->load('roles');
        });

        $token = $superAdmin->createToken('super-admin-auth', ['super_admin'])->plainTextToken;

        return $this->created([
            'user' => new SeekerAccountResource($superAdmin),
            'token' => $token,
            'token_type' => 'Bearer',
            'abilities' => ['super_admin'],
        ], 'Super admin registered successfully.');
    }

    public function loginSuperAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->with('roles')
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if (!$user->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'message' => 'This account is not authorized for super admin APIs.',
            ], 403);
        }

        $token = $user->createToken('super-admin-auth', ['super_admin'])->plainTextToken;

        return $this->success([
            'user' => new SeekerAccountResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
            'abilities' => ['super_admin'],
        ], 'Login successful.');
    }

    private function resolveAdminAbilities(User $user): array
    {
        if ($user->hasRole('admin')) {
            return ['admin'];
        }

        if ($user->hasRole('admin_staff')) {
            return ['admin_staff'];
        }

        return [];
    }
}
