<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class MarketingReel extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['organization_id', 'model_type', 'model_id', 'file_url', 'sort_order'];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
