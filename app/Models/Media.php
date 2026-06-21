<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'property_listing_media';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'property_listing_id',
        'file_url',
        'media_type',
        'is_primary',
        'sort_order',
    ];

    public function propertyListing(): BelongsTo
    {
        return $this->belongsTo(PropertyListing::class);
    }
}
