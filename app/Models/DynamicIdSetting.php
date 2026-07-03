<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DynamicIdSetting extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'entity_type',
        'prefix',
        'separator',
        'include_year',
        'include_month',
        'number_padding',
        'starting_number',
        'current_number',
        'reset_frequency',
        'last_reset_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'include_year' => 'boolean',
            'include_month' => 'boolean',
            'number_padding' => 'integer',
            'starting_number' => 'integer',
            'current_number' => 'integer',
            'last_reset_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
