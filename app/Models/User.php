<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Permission;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes, HasApiTokens;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'generated_id',
        'name',
        'email',
        'mobile_number',
        'mobile_verified_at',
        'display_name',
        'password_hash',
        'id_verified',
        'organization_id',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_hash' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (blank($user->generated_id)) {
                $user->generated_id = app(\App\Services\DynamicIdGeneratorService::class)->generate('users');
            }
        });
    }

    public function getDisplayIdAttribute(): string
    {
        return $this->generated_id ?: $this->id;
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        return static::query()
            ->where('generated_id', $value)
            ->orWhere($this->getKeyName(), $value)
            ->first();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->wherePivotNull('deleted_at')
            ->withPivot(['id', 'organization_id'])
            ->withTimestamps();
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'user_roles');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function hasActiveSubscriptionAccess(): bool
    {
        if ($this->isSuperAdmin() || $this->isGlobalStaff()) {
            return true;
        }

        $this->loadMissing(['organization.plan', 'organization.currentSubscription']);

        $organization = $this->organization;
        if (!$organization) {
            return false;
        }

        if ($this->isTrialActive()) {
            return true;
        }

        if ($organization->plan_id && $organization->currentSubscription?->status === 'active') {
            return true;
        }

        return false;
    }

    public function isTrialActive(): bool
    {
        if ($this->isSuperAdmin() || $this->isGlobalStaff()) {
            return true;
        }

        $this->loadMissing('organization');

        $trialEndsAt = $this->organization?->trial_ends_at;

        return $trialEndsAt instanceof Carbon && now()->lessThanOrEqualTo($trialEndsAt);
    }

    public function subscriptionStatus(): string
    {
        if ($this->isSuperAdmin() || $this->isGlobalStaff()) {
            return 'active';
        }

        $this->loadMissing(['organization.plan', 'organization.currentSubscription']);

        $organization = $this->organization;
        if (!$organization) {
            return 'inactive';
        }

        if ($organization->plan_id && $organization->currentSubscription?->status === 'active') {
            return 'active';
        }

        if ($this->isTrialActive()) {
            return 'trialing';
        }

        return 'expired';
    }

    public function subscriptionSummary(): array
    {
        $this->loadMissing(['organization.plan', 'organization.currentSubscription']);

        return [
            'status' => $this->subscriptionStatus(),
            'is_trial_active' => $this->isTrialActive(),
            'trial_started_at' => $this->organization?->trial_started_at?->toISOString(),
            'trial_ends_at' => $this->organization?->trial_ends_at?->toISOString(),
            'subscription_activated_at' => $this->organization?->subscription_activated_at?->toISOString(),
            'plan' => $this->organization?->plan ? [
                'id' => $this->organization->plan->id,
                'name' => $this->organization->plan->name,
                'price' => (int) $this->organization->plan->price,
            ] : null,
            'current_subscription' => $this->organization?->currentSubscription ? [
                'id' => $this->organization->currentSubscription->id,
                'status' => $this->organization->currentSubscription->status,
                'current_period_start' => $this->organization->currentSubscription->current_period_start?->toISOString(),
                'current_period_end' => $this->organization->currentSubscription->current_period_end?->toISOString(),
            ] : null,
        ];
    }

    public function propertyListings(): HasMany
    {
        return $this->hasMany(PropertyListing::class, 'creator_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
            ->withPivot(['id', 'effect'])
            ->withTimestamps();
    }

    public function planRequests(): HasMany
    {
        return $this->hasMany(PlanRequest::class, 'requested_by');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function assignedInquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'staff_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function seekerProfile(): HasOne
    {
        return $this->hasOne(SeekerProfile::class);
    }

    public function seekerSavedSearches(): HasMany
    {
        return $this->hasMany(SeekerSavedSearch::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function hasRole(string $roleName, ?string $organizationId = null): bool
    {
        $roles = $this->roles instanceof Collection ? $this->roles : $this->roles()->get();

        return $roles
            ->filter(fn(Role $role): bool => $role->name === $roleName)
            ->contains(function (Role $role) use ($organizationId): bool {
                if ($organizationId === null) {
                    return true;
                }

                return $role->pivot?->organization_id === $organizationId;
            });
    }

    public function hasAnyRole(array $roleNames, ?string $organizationId = null): bool
    {
        foreach ($roleNames as $roleName) {
            if ($this->hasRole($roleName, $organizationId)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isGlobalStaff(): bool
    {
        return $this->hasRole('super_admin_employee');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (!$this->hasActiveSubscriptionAccess() && !$this->isGlobalStaff()) {
            return false;
        }

        $this->loadMissing(['roles.permissions', 'directPermissions']);

        if ($this->directPermissions->firstWhere('name', $permission)?->pivot?->effect === 'deny') {
            return false;
        }

        if ($this->directPermissions->firstWhere('name', $permission)?->pivot?->effect === 'allow') {
            return true;
        }

        foreach ($this->roles as $role) {
            if ($role->permissions->contains('name', $permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    public function getAllPermissions(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Permission::query()->orderBy('module')->orderBy('action')->get();
        }

        if (!$this->hasActiveSubscriptionAccess() && !$this->isGlobalStaff()) {
            return collect();
        }

        $this->loadMissing(['roles.permissions', 'directPermissions']);

        $denyIds = $this->directPermissions
            ->filter(fn (Permission $permission): bool => $permission->pivot?->effect === 'deny')
            ->pluck('id')
            ->all();

        $allowIds = $this->directPermissions
            ->filter(fn (Permission $permission): bool => $permission->pivot?->effect === 'allow')
            ->pluck('id')
            ->all();

        $roleIds = $this->roles
            ->flatMap(fn (Role $role): Collection => $role->permissions->pluck('id'))
            ->unique()
            ->diff($denyIds)
            ->merge($allowIds)
            ->unique()
            ->values()
            ->all();

        return Permission::query()
            ->whereIn('id', $roleIds)
            ->orderBy('module')
            ->orderBy('action')
            ->get();
    }

    public function getPermissionNames(): array
    {
        return $this->getAllPermissions()->pluck('name')->values()->all();
    }
}
