<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'invoice_number',
        'plan_request_id',
        'order_id',
        'organization_id',
        'user_id',
        'template_key',
        'status',
        'payment_status',
        'currency',
        'subtotal',
        'tax_amount',
        'total_amount',
        'issue_date',
        'due_date',
        'supplier_name',
        'supplier_abn',
        'supplier_email',
        'supplier_address',
        'recipient_name',
        'recipient_abn',
        'recipient_email',
        'recipient_address',
        'line_items',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'issue_date' => 'datetime',
            'due_date' => 'datetime',
            'line_items' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function planRequest(): BelongsTo
    {
        return $this->belongsTo(PlanRequest::class, 'plan_request_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        return $this->newQuery()
            ->where('invoice_number', $value)
            ->orWhereKey($value)
            ->first();
    }
}
