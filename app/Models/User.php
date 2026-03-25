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
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes, HasApiTokens;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
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

    public function propertyListings(): HasMany
    {
        return $this->hasMany(PropertyListing::class, 'creator_id');
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
}
