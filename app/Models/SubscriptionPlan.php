<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'stripe_price_id',
        'price',
        'property_limit',
        'popular',
        'features',
        'is_active',
        'staff_seat_limit',
        'has_visitor_analytics',
        'ranking_priority',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'property_limit' => 'integer',
            'popular' => 'boolean',
            'is_active' => 'boolean',
            'has_visitor_analytics' => 'boolean',
            'features' => 'array',
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
}
