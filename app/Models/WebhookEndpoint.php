<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WebhookEndpoint extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'name',
        'endpoint_url',
        'secret_key',
        'description',
        'status',
        'events',
        'retry_count',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
        ];
    }

    protected $hidden = [
        'secret_key',
    ];

    protected $appends = [
        'health_status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(WebhookDeliveryLog::class, 'webhook_endpoint_id');
    }

    public function latestDelivery(): HasOne
    {
        return $this->hasOne(WebhookDeliveryLog::class, 'webhook_endpoint_id')->latestOfMany();
    }

    public function getHealthStatusAttribute(): string
    {
        if ($this->status !== 'active') {
            return 'disabled';
        }

        $latestStatus = $this->latestDelivery?->delivery_status;
        if (!$latestStatus) {
            return 'warning';
        }

        if (in_array($latestStatus, ['failed', 'dead_letter'], true)) {
            return 'critical';
        }

        if (in_array($latestStatus, ['pending', 'retrying'], true)) {
            return 'warning';
        }

        return 'healthy';
    }
}
