<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanRequest extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'request_code',
        'organization_id',
        'requested_by',
        'plan_id',
        'company_name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'requested_plan_name',
        'billing_cycle',
        'message',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function getDisplayIdAttribute(): string
    {
        return $this->request_code ?: $this->formatDisplayId('PRQ');
    }

    private function formatDisplayId(string $prefix): string
    {
        $raw = str_replace('-', '', (string) $this->id);

        return sprintf('%s-%s', $prefix, strtoupper(substr($raw, 0, 8)));
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        return static::query()
            ->where('request_code', $value)
            ->orWhere($this->getKeyName(), $value)
            ->first();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class, 'plan_request_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'plan_request_id');
    }
}
