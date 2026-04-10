<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoleTraderProfile extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'organization_id',
        'trading_name',
        'abn',
        'trade_license_number',
        'primary_service_postcode',
        'service_radius_km',
        'profile_image_url',
        'professional_bio',
        'public_liability_insurer',
        'policy_number',
        'policy_expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'policy_expiry_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}

