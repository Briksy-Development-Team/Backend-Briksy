<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['user_id', 'name', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function properties(): BelongsToMany
    {
        return $this->morphedByMany(PropertyListing::class, 'collectable', 'collection_items', 'collection_id', 'collectable_id')
            ->withTimestamps();
    }

    public function services(): BelongsToMany
    {
        return $this->morphedByMany(Service::class, 'collectable', 'collection_items', 'collection_id', 'collectable_id')
            ->withTimestamps();
    }

    public function organizations(): BelongsToMany
    {
        return $this->morphedByMany(Organization::class, 'collectable', 'collection_items', 'collection_id', 'collectable_id')
            ->withTimestamps();
    }

    public function items(): HasMany
    {
        return $this->hasMany(CollectionItem::class);
    }
}
