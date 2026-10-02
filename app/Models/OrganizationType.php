<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationType extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'slug',
        'module',
        'display_name',
        'capability_profile',
        'plan_family',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getLabelAttribute(): string
    {
        return $this->display_name ?: $this->name;
    }

    public function isAgentType(): bool
    {
        return $this->module === 'Agents';
    }

    public function hasCapability(string $profile): bool
    {
        return $this->capability_profile === $profile;
    }

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'type_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'type_id');
    }

    public function serviceGroups(): HasMany
    {
        return $this->hasMany(ServiceGroup::class, 'type_id');
    }
}
