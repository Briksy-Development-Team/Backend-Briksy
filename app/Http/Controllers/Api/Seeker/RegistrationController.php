<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\RegisterSeekerRequest;
use App\Http\Resources\Seeker\SeekerAccountResource;
use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\Role;
use App\Models\User;
use App\Services\AbnLookupService;
use App\Services\ReferralService;
use App\Services\NotificationService;
use App\Services\Webhooks\WebhookDispatcherService;
use App\Exceptions\AbnLookupUnavailableException;
use App\Exceptions\AbnLookupVerificationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly ReferralService $referralService,
        private readonly AbnLookupService $abnLookupService,
        private readonly WebhookDispatcherService $webhookDispatcher
    )
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

        $this->webhookDispatcher->dispatch(
            'user.created',
            [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => 'seeker',
            ],
            $user->organization,
            $user,
            sprintf('user.created:%s', $user->id)
        );

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

        if ($user->organization) {
            $this->webhookDispatcher->dispatch(
                'auth.login',
                [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => 'seeker',
                ],
                $user->organization,
                $user,
                sprintf('auth.login:%s', $user->id)
            );
        }

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
        $user = $request->user();
        if ($user?->organization) {
            $this->webhookDispatcher->dispatch(
                'auth.logout',
                [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->roles->pluck('name')->first(),
                ],
                $user->organization,
                $user,
                sprintf('auth.logout:%s', $user->id)
            );
        }
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
            'referral_code' => ['nullable', 'string', 'max:50'],
            'abn_number' => ['required', 'string', 'size:11'],
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

        try {
            $verification = $this->abnLookupService->verify($abn, $businessType);
        } catch (AbnLookupVerificationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], $exception->httpStatus());
        } catch (AbnLookupUnavailableException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], $exception->httpStatus());
        }

        $adminUser = DB::transaction(function () use ($validated, $businessType, $abn, $organizationTypeSlug, $verification): User {
            $organizationType = OrganizationType::query()
                ->where('slug', $organizationTypeSlug)
                ->firstOrFail();
            $referrer = $this->referralService->resolveReferrer($validated['referral_code'] ?? null);

            $slugBase = Str::slug($validated['business_name']);
            $slug = $slugBase;
            $suffix = 1;
            while (Organization::query()->where('slug', $slug)->exists()) {
                $slug = $slugBase . '-' . $suffix++;
            }

            $organization = Organization::create([
                'name' => $verification['entityName'] ?: $validated['business_name'],
                'trading_name' => $validated['trading_name'] ?? null,
                'contact_email' => $validated['contact_email'] ?? $validated['email'],
                'contact_phone' => $validated['contact_phone'] ?? null,
                'abn' => $abn,
                'abn_verified' => true,
                'abn_verified_at' => now(),
                'entity_name' => $verification['entityName'] ?: $validated['business_name'],
                'entity_type' => $verification['entityType'] ?? null,
                'entity_status' => $verification['entityStatus'] ?? null,
                'gst_registered' => (bool) ($verification['gstRegistered'] ?? false),
                'abn_effective_from' => $verification['effectiveFrom'] ?? null,
                'abn_raw_response' => $verification['rawResponse'] ?? null,
                'referral_code' => $this->referralService->generateCode(),
                'referred_by_organization_id' => $referrer?->id,
                'business_type' => $businessType,
                'business_verification_status' => 'verified',
                'address' => $validated['address'] ?? null,
                'state' => $verification['state'] ?? ($validated['state'] ?? null),
                'postcode' => $verification['postcode'] ?? ($validated['postcode'] ?? null),
                'plan_id' => null,
                'type_id' => $organizationType->id,
                'ranking_priority' => 1,
                'avg_org_rating' => 0,
                'slug' => $slug,
                'stripe_customer_id' => null,
                'is_verified' => true,
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
            $this->webhookDispatcher->dispatch(
                'company.created',
                [
                    'company_id' => $adminUser->organization_id,
                    'name' => $adminUser->organization?->name ?? $validated['business_name'],
                    'business_type' => $businessType,
                ],
                $adminUser->organization,
                $adminUser,
                sprintf('company.created:%s', $adminUser->organization_id)
            );

            $this->webhookDispatcher->dispatch(
                'user.created',
                [
                    'user_id' => $adminUser->id,
                    'email' => $adminUser->email,
                    'name' => $adminUser->name,
                    'role' => 'admin',
                ],
                $adminUser->organization,
                $adminUser,
                sprintf('user.created:%s', $adminUser->id)
            );

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

        if ($user->organization) {
            $this->webhookDispatcher->dispatch(
                'auth.login',
                [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $abilities[0] ?? null,
                ],
                $user->organization,
                $user,
                sprintf('auth.login:%s', $user->id)
            );
        }

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

        if ($organizationId) {
            $this->webhookDispatcher->dispatch(
                'user.created',
                [
                    'user_id' => $staffUser->id,
                    'email' => $staffUser->email,
                    'name' => $staffUser->name,
                    'role' => 'admin_staff',
                ],
                $staffUser->organization,
                $authUser,
                sprintf('user.created:%s', $staffUser->id)
            );

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
        }

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

        if (!$user->hasAnyRole(['super_admin', 'super_admin_employee'])) {
            return response()->json([
                'success' => false,
                'message' => 'This account is not authorized for super admin APIs.',
            ], 403);
        }

        $abilities = $this->resolveSuperAdminAbilities($user);
        $token = $user->createToken('super-admin-auth', $abilities)->plainTextToken;

        return $this->success([
            'user' => new SeekerAccountResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
            'abilities' => $abilities,
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

    private function resolveSuperAdminAbilities(User $user): array
    {
        if ($user->hasRole('super_admin')) {
            return ['super_admin'];
        }

        if ($user->hasRole('super_admin_employee')) {
            return ['super_admin_employee'];
        }

        return [];
    }

}
