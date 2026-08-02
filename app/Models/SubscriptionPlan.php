<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'plan_family',
        'description',
        'monthly_price',
        'yearly_price',
        'currency',
        'billing_enabled',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
        'trial_days',
        'stripe_price_id',
        'price',
        'property_limit',
        'popular',
        'features',
        'permissions',
        'is_active',
        'staff_seat_limit',
        'has_visitor_analytics',
        'ranking_priority',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'billing_enabled' => 'boolean',
            'trial_days' => 'integer',
            'price' => 'integer',
            'property_limit' => 'integer',
            'popular' => 'boolean',
            'is_active' => 'boolean',
            'has_visitor_analytics' => 'boolean',
            'features' => 'array',
            'permissions' => 'array',
        ];
    }

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'plan_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'plan_id');
    }

    public function planRequests(): HasMany
    {
        return $this->hasMany(PlanRequest::class, 'plan_id');
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'plan_addons', 'plan_id', 'addon_id')
            ->withPivot(['included_quantity', 'is_included'])
            ->withTimestamps();
    }
}
