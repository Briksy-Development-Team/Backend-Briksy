<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceGroup extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'type_id',
        'name',
        'slug',
        'description',
    ];

    public function organizationType(): BelongsTo
    {
        return $this->belongsTo(OrganizationType::class, 'type_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_group_services', 'service_group_id', 'service_id')
            ->withTimestamps();
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'org_service_groups', 'service_group_id', 'organization_id')
            ->withPivot(['id', 'description', 'package_price', 'is_active'])
            ->withTimestamps();
    }
}
