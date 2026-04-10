<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropertyFeature extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'group_id',
        'name',
        'slug',
        'sort_order',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(PropertyFeatureGroup::class, 'group_id');
    }

    public function propertyListings(): BelongsToMany
    {
        return $this->belongsToMany(PropertyListing::class, 'property_listing_features', 'feature_id', 'property_listing_id')
            ->wherePivotNull('deleted_at')
            ->withPivot(['id'])
            ->withTimestamps();
    }
}

