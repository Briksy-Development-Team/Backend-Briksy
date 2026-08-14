<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasImmutableGeneratedId;
use App\Services\DynamicIdGeneratorService;

class Service extends Model
{
    use HasUuids, HasImmutableGeneratedId, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'type_id',
        'organization_id',
        'name',
        'title',
        'category',
        'slug',
        'generated_id',
        'description',
        'service_area',
        'service_area_geometry',
        'rate_from',
        'rate_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'service_area_geometry' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Service $service): void {
            if (blank($service->generated_id)) {
                $service->generated_id = app(DynamicIdGeneratorService::class)->generate('services');
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

    public function getDisplayIdAttribute(): string
    {
        return $this->generated_id ?: $this->formatDisplayId('SRV');
    }

    private function formatDisplayId(string $prefix): string
    {
        $raw = str_replace('-', '', (string) $this->id);

        return sprintf('%s-%s', $prefix, strtoupper(substr($raw, 0, 8)));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function organizationType(): BelongsTo
    {
        return $this->belongsTo(OrganizationType::class, 'type_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_services', 'service_id', 'organization_id')
            ->withPivot(['id', 'description', 'starting_price', 'is_active'])
            ->withTimestamps();
    }

    public function serviceGroups(): BelongsToMany
    {
        return $this->belongsToMany(ServiceGroup::class, 'service_group_services', 'service_id', 'service_group_id')
            ->withTimestamps();
    }
}
