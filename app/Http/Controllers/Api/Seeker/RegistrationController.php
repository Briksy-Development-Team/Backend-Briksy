<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\RegisterSeekerRequest;
use App\Http\Resources\Seeker\SeekerAccountResource;
use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function store(RegisterSeekerRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            // Support frontends that submit `first` and `last` instead of a single `name`.
            $name = $request->input('name');
            if (empty($name)) {
                $first = trim((string) $request->input('first'));
                $last = trim((string) $request->input('last'));
                $name = trim(sprintf('%s %s', $first, $last));
            }

            $user = User::create([
                'name' => $name,
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

            return $user->load(['roles.permissions', 'directPermissions']);
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
            ->with(['roles.permissions', 'directPermissions'])
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

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing(['roles.permissions', 'directPermissions']);

        return $this->success([
            'user' => new SeekerAccountResource($user),
        ], 'Authenticated user fetched successfully.');
    }

    public function logoutSeeker(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->success([], 'Logout successful.');
    }

    public function registerAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first' => ['required', 'string', 'max:120'],
            'last' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'business_name' => ['required', 'string', 'max:200'],
            'trading_name' => ['nullable', 'string', 'max:200'],
            'business_type' => ['required', 'in:organisation,company,solo_trader'],
            'abn_number' => [
                'required',
                'string',
                'size:11',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $normalized = preg_replace('/\s+/', '', (string) $value);
                    if (!preg_match('/^\d{11}$/', $normalized) || !$this->isValidAbn($normalized)) {
                        $fail('The ABN number is invalid.');
                    }
                },
            ],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'state' => ['nullable', 'string', 'max:50'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $businessType = $validated['business_type'];
        $abn = preg_replace('/\s+/', '', $validated['abn_number']);
        $organizationTypeSlug = $businessType === 'solo_trader' ? 'solo-traders' : 'property-management';

        $adminUser = DB::transaction(function () use ($validated, $businessType, $abn, $organizationTypeSlug): User {
            $organizationType = OrganizationType::query()
                ->where('slug', $organizationTypeSlug)
                ->firstOrFail();

            $slugBase = Str::slug($validated['business_name']);
            $slug = $slugBase;
            $suffix = 1;
            while (Organization::query()->where('slug', $slug)->exists()) {
                $slug = $slugBase . '-' . $suffix++;
            }

            $organization = Organization::create([
                'name' => $validated['business_name'],
                'trading_name' => $validated['trading_name'] ?? null,
                'contact_email' => $validated['contact_email'] ?? $validated['email'],
                'contact_phone' => $validated['contact_phone'] ?? null,
                'abn' => $abn,
                'business_type' => $businessType,
                'business_verification_status' => 'pending',
                'address' => $validated['address'] ?? null,
                'state' => $validated['state'] ?? null,
                'postcode' => $validated['postcode'] ?? null,
                'plan_id' => null,
                'type_id' => $organizationType->id,
                'ranking_priority' => 1,
                'avg_org_rating' => 0,
                'slug' => $slug,
                'stripe_customer_id' => null,
                'is_verified' => false,
                'trial_started_at' => now(),
                'trial_ends_at' => now()->addDays(15),
                'subscription_status' => 'trialing',
                'subscription_activated_at' => null,
            ]);

            $adminUser = User::create([
                'name' => trim($validated['first'] . ' ' . $validated['last']),
                'email' => $validated['email'],
                'password_hash' => $validated['password'],
                'organization_id' => $organization->id,
                'id_verified' => false,
            ]);

            $adminRole = Role::query()->firstOrCreate(
                ['name' => 'admin'],
                ['scope' => 'global', 'is_system' => true]
            );

            $adminUser->roles()->syncWithoutDetaching([
                $adminRole->id => [
                    'id' => (string) str()->uuid(),
                    'organization_id' => $organization->id,
                ],
            ]);

            return $adminUser->load(['roles.permissions', 'directPermissions', 'organization.plan', 'organization.currentSubscription']);
        });

        if ($adminUser->organization_id) {
            $this->notificationService->notifySuperAdmins(
                $this->notificationService->buildPayload(
                    'company_signup',
                    'New company signup',
                    sprintf('Company "%s" registered a new admin account.', $adminUser->organization?->name ?? $validated['business_name']),
                    Organization::class,
                    $adminUser->organization_id,
                    "/super-admin/companies/{$adminUser->organization_id}",
                    'high',
                    $adminUser->id,
                    $adminUser->organization_id
                ),
                'New company signup',
                'Review company'
            );
        }

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
            ->with(['roles.permissions', 'directPermissions', 'organization.plan', 'organization.currentSubscription'])
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

        if ($user->organization && !$user->organization->trial_started_at && !$user->organization->plan_id) {
            $user->organization->forceFill([
                'trial_started_at' => now(),
                'trial_ends_at' => now()->addDays(15),
                'subscription_status' => 'trialing',
            ])->save();
            $user->load(['organization.plan', 'organization.currentSubscription']);
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
            'first' => ['required', 'string', 'max:120'],
            'last' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
        ]);

        $organizationId = $validated['organization_id'] ?? $authUser->organization_id;

        $staffUser = DB::transaction(function () use ($validated, $organizationId): User {
            $staffUser = User::create([
                'name' => $validated['first'] . ' ' . $validated['last'],
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

            return $staffUser->load(['roles.permissions', 'directPermissions', 'organization.plan', 'organization.currentSubscription']);
        });

        $this->notificationService->notifyAdminsForOrganisation(
            $organizationId,
            $this->notificationService->buildPayload(
                'admin_user_invited',
                'Admin user invited',
                sprintf('A new staff user "%s" was invited.', $staffUser->name),
                User::class,
                $staffUser->id,
                '/admin/users',
                'normal',
                $authUser->id,
                $organizationId
            ),
            'Admin user invited',
            'View users'
        );

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

            return $superAdmin->load(['roles.permissions', 'directPermissions']);
        });

        if ($superRole->users()->count() > 1) {
            $this->notificationService->notifySuperAdmins(
                $this->notificationService->buildPayload(
                    'super_admin_created',
                    'New super admin created',
                    sprintf('A new super admin account "%s" has been created.', $superAdmin->name),
                    User::class,
                    $superAdmin->id,
                    '/super-admin/staff',
                    'normal',
                    $superAdmin->id,
                    null
                ),
                'New super admin',
                'View staff'
            );
        }

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
            ->with(['roles.permissions', 'directPermissions'])
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

    private function isValidAbn(string $abn): bool
    {
        if (!preg_match('/^\d{11}$/', $abn)) {
            return false;
        }

        $digits = array_map('intval', str_split($abn));
        $digits[0] -= 1;

        $weights = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];
        $sum = 0;

        foreach ($digits as $index => $digit) {
            $sum += $digit * $weights[$index];
        }

        return $sum % 89 === 0;
    }
}
