<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'type_id',
        'organization_id',
        'name',
        'title',
        'category',
        'slug',
        'description',
        'service_area',
        'rate_from',
        'rate_to',
        'is_active',
    ];

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
