<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasImmutableGeneratedId;
use App\Models\ActivityLog;
use App\Services\DynamicIdGeneratorService;
use App\Support\Properties\PropertyWorkflow;

class PropertyListing extends Model
{
    use HasUuids, HasImmutableGeneratedId, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'org_id',
        'creator_id',
        'generated_id',
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
        'formatted_address',
        'place_id',
        'status',
        'suburb',
        'state',
        'postcode',
        'country',
        'location_verified',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'published_at',
        'location_verified_by',
        'location_verified_at',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'location_verified_at' => 'datetime',
            'location_verified' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PropertyListing $propertyListing): void {
            if (blank($propertyListing->generated_id)) {
                $propertyListing->generated_id = app(DynamicIdGeneratorService::class)->generate('properties');
            }
        });
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        return static::query()
            ->where('generated_id', $value)
            ->orWhere($this->getKeyName(), $value)
            ->first();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function locationVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'location_verified_by');
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

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'subject_id')
            ->where('module', PropertyWorkflow::MODULE)
            ->orderByDesc('created_at');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PropertyWorkflow::STATUS_PUBLISHED)
            ->where('location_verified', true);
    }

    public function scopeVisibleToSeekers(Builder $query): Builder
    {
        return $query->published();
    }
}
