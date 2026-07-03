<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'organization_id',
        'subscription_plan_id',
        'billing_cycle',
        'currency',
        'amount',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_checkout_session_id',
        'latest_invoice_id',
        'status',
        'payment_status',
        'current_period_start',
        'current_period_end',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function addons()
    {
        return $this->hasMany(SubscriptionAddon::class, 'subscription_id');
    }

    public function events()
    {
        return $this->hasMany(SubscriptionEvent::class, 'subscription_id');
    }
}
