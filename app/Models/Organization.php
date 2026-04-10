<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'contact_email',
        'contact_phone',
        'abn',
        'acn',
        'plan_id',
        'type_id',
        'ranking_priority',
        'avg_org_rating',
        'stripe_customer_id',
        'is_verified',
        'slug',
        'logo_url',
        'brand_primary_color',
        'brand_secondary_color',
        'licensed_staff_seats',
    ];

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

    public function propertyListings(): HasMany
    {
        return $this->hasMany(PropertyListing::class, 'org_id');
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

    public function soleTraderProfiles(): HasMany
    {
        return $this->hasMany(SoleTraderProfile::class, 'organization_id');
    }
}

