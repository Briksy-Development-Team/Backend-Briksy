<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class CheckoutInvitation extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'inquiry_id', 'user_id', 'organization_id', 'plan_id', 'created_by', 'token',
        'status', 'billing_cycle', 'addons', 'stripe_checkout_session_id',
        'stripe_payment_intent_id', 'expires_at', 'paid_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'addons' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function inquiry(): BelongsTo { return $this->belongsTo(Inquiry::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function plan(): BelongsTo { return $this->belongsTo(SubscriptionPlan::class, 'plan_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function isUsable(): bool
    {
        return $this->status === 'active' && now()->lessThan($this->expires_at);
    }
}
