<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasImmutableGeneratedId;
use App\Services\DynamicIdGeneratorService;

class Organization extends Model
{
    use HasUuids, HasImmutableGeneratedId, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'generated_id',
        'referral_code',
        'referred_by_organization_id',
        'name',
        'trading_name',
        'entity_name',
        'entity_type',
        'entity_status',
        'contact_email',
        'contact_phone',
        'abn',
        'abn_verified',
        'abn_verified_at',
        'gst_registered',
        'abn_effective_from',
        'abn_raw_response',
        'business_type',
        'business_verification_status',
        'address',
        'state',
        'postcode',
        'plan_id',
        'type_id',
        'ranking_priority',
        'avg_org_rating',
        'stripe_customer_id',
        'is_verified',
        'slug',
        'trial_started_at',
        'trial_ends_at',
        'subscription_status',
        'subscription_activated_at',
    ];

    protected function casts(): array
    {
        return [
            'abn_verified' => 'boolean',
            'abn_verified_at' => 'datetime',
            'gst_registered' => 'boolean',
            'abn_effective_from' => 'date',
            'abn_raw_response' => 'array',
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'subscription_activated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            if (blank($organization->generated_id)) {
                $organization->generated_id = app(DynamicIdGeneratorService::class)->generate('organizations');
            }

            if (!blank($organization->referral_code)) {
                return;
            }

            $organization->referral_code = app(DynamicIdGeneratorService::class)->generate('referrals');
        });
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

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'organization_id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'organization_id');
    }

    public function organizationType(): BelongsTo
    {
        return $this->belongsTo(OrganizationType::class, 'type_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'organization_id');
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'organization_id')
            ->whereIn('status', ['active', 'trialing'])
            ->latestOfMany();
    }

    public function subscriptionStatus(): string
    {
        if ($this->plan_id && $this->currentSubscription?->status === 'active') {
            return 'active';
        }

        if ($this->trial_ends_at && now()->lessThanOrEqualTo($this->trial_ends_at)) {
            return 'trialing';
        }

        return $this->subscription_status ?? 'expired';
    }

    public function propertyListings(): HasMany
    {
        return $this->hasMany(PropertyListing::class, 'org_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'organization_id');
    }

    public function planRequests(): HasMany
    {
        return $this->hasMany(PlanRequest::class, 'organization_id');
    }

    public function referredByOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'referred_by_organization_id');
    }

    public function referredOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'referred_by_organization_id');
    }

    public function companySettings(): HasMany
    {
        return $this->hasMany(CompanySetting::class, 'organization_id');
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'organization_services', 'organization_id', 'service_id')
            ->withPivot(['id', 'description', 'starting_price', 'is_active'])
            ->withTimestamps();
    }

    public function serviceGroups(): BelongsToMany
    {
        return $this->belongsToMany(ServiceGroup::class, 'organization_service_groups', 'organization_id', 'service_group_id')
            ->withPivot(['id', 'description', 'package_price', 'is_active'])
            ->withTimestamps();
    }
}
