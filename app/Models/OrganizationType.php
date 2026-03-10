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
    ];

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
