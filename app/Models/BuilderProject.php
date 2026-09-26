<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class BuilderProject extends Model
{
    use HasUuids, SoftDeletes;
    protected $keyType = 'string'; public $incrementing = false;
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function media(): HasMany { return $this->hasMany(Media::class, 'builder_project_id')->orderBy('sort_order'); }
    protected $fillable = ['organization_id','created_by','name','project_type','status','description','features','location','state','postcode','latitude','longitude','submitted_at','reviewed_by','reviewed_at','rejection_reason','published_at'];
    protected function casts(): array { return ['latitude' => 'float', 'longitude' => 'float', 'features' => 'array', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'published_at' => 'datetime']; }
}
