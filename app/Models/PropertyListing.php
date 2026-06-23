<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropertyListing extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'org_id',
        'creator_id',
        'property_type_id',
        'avg_prop_rating',
        'address_line_1',
        'address_line_2',
        'latitude',
        'longitude',
        'title',
        'description',
        'address',
        'full_address',
<<<<<<< Updated upstream
=======
        'formatted_address',
        'place_id',
>>>>>>> Stashed changes
        'status',
        'suburb',
        'state',
        'postcode',
        'country',
        'location_verified',
        'embedding',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'property_type_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'property_listing_id')->orderBy('sort_order');
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'property_listing_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'property_listing_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'Published');
    }
}
