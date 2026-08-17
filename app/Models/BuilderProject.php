<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BuilderProject extends Model
{
    use HasUuids, SoftDeletes;
    protected $keyType = 'string'; public $incrementing = false;
    protected $fillable = ['organization_id','created_by','name','project_type','status','description','location','state','postcode','latitude','longitude'];
    protected function casts(): array { return ['latitude' => 'float', 'longitude' => 'float']; }
}
