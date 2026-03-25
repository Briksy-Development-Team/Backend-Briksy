<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeekerProfile extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'seeker_profiles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'current_postcode',
        'preferred_budget_min',
        'preferred_budget_max',
    ];

    protected function casts(): array
    {
        return [
            'preferred_budget_min' => 'decimal:2',
            'preferred_budget_max' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
