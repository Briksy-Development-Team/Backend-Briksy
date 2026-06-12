<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Auth\SocialLoginRequest;
use App\Http\Resources\Seeker\SeekerAccountResource;
use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    public function login(string $provider, SocialLoginRequest $request): JsonResponse
    {
        if (!in_array($provider, ['google', 'facebook'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported social provider.',
            ], 422);
        }

        try {
            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->userFromToken($request->input('access_token'));
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to validate social token.',
            ], 401);
        }

        $email = $socialUser->getEmail();
        $providerId = $socialUser->getId();
        $name = $socialUser->getName() ?: $socialUser->getNickname() ?: 'Social User';
        $avatar = $socialUser->getAvatar();

        $roleName = $request->input('role');

        $user = DB::transaction(function () use ($provider, $providerId, $email, $name, $avatar, $socialUser, $roleName): User {
            $account = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_user_id', $providerId)
                ->first();

            if ($account) {
                $account->update([
                    'provider_email' => $email,
                    'provider_avatar' => $avatar,
                    'provider_access_token' => $socialUser->token ?? null,
                    'provider_refresh_token' => $socialUser->refreshToken ?? null,
                ]);

                return $account->user()->with(['roles.permissions', 'directPermissions'])->first();
            }

            $user = null;

            if ($email) {
                $user = User::query()->where('email', $email)->first();
            }

            if (!$user) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email ?: Str::uuid().'@social.local',
                    'password_hash' => Str::random(32),
                    'organization_id' => null,
                    'id_verified' => false,
                ]);
            }

            SocialAccount::query()->create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $providerId,
                'provider_email' => $email,
                'provider_avatar' => $avatar,
                'provider_access_token' => $socialUser->token ?? null,
                'provider_refresh_token' => $socialUser->refreshToken ?? null,
            ]);

            if ($roleName) {
                $role = Role::query()->where('name', $roleName)->first();

                if ($role) {
                    $user->roles()->syncWithoutDetaching([
                        $role->id => [
                            'id' => (string) Str::uuid(),
                            'organization_id' => null,
                        ],
                    ]);
                }
            }

            return $user->load(['roles.permissions', 'directPermissions']);
        });

        $abilities = $this->resolveAbilities($user, $roleName);
        $token = $user->createToken($this->tokenNameFor($abilities), $abilities)->plainTextToken;

        return $this->success([
            'user' => new SeekerAccountResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
            'abilities' => $abilities,
        ], 'Login successful.');
    }

    private function resolveAbilities(User $user, ?string $requestedRole): array
    {
        if ($requestedRole === 'admin' && $user->hasRole('admin')) {
            return ['admin'];
        }

        if ($requestedRole === 'admin_staff' && $user->hasRole('admin_staff')) {
            return ['admin_staff'];
        }

        if ($user->hasRole('admin')) {
            return ['admin'];
        }

        if ($user->hasRole('admin_staff')) {
            return ['admin_staff'];
        }

        return ['seeker'];
    }

    private function tokenNameFor(array $abilities): string
    {
        if ($abilities === ['admin']) {
            return 'admin-auth';
        }

        if ($abilities === ['admin_staff']) {
            return 'admin-staff-auth';
        }

        return 'seeker-auth';
    }
}
