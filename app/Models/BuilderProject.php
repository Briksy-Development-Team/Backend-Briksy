<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuilderProject extends Model
{
    use HasUuids, SoftDeletes;
    protected $keyType = 'string'; public $incrementing = false;
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    protected $fillable = ['organization_id','created_by','name','project_type','status','description','features','location','state','postcode','latitude','longitude','submitted_at','reviewed_by','reviewed_at','rejection_reason','published_at'];
    protected function casts(): array { return ['latitude' => 'float', 'longitude' => 'float', 'features' => 'array', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'published_at' => 'datetime']; }
}
