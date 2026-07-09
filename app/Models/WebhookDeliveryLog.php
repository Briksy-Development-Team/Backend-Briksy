<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDeliveryLog extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'webhook_endpoint_id',
        'company_id',
        'event_id',
        'event',
        'endpoint_url',
        'signature',
        'deduplication_key',
        'payload',
        'response_body',
        'http_status',
        'response_time_ms',
        'delivery_status',
        'attempt_count',
        'retry_count',
        'error_message',
        'last_attempt_at',
        'next_retry_at',
        'delivered_at',
        'failed_at',
        'dead_lettered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_attempt_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'dead_lettered_at' => 'datetime',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'company_id');
    }
}
