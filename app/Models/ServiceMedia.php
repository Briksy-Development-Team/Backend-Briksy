<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceMedia extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'service_media';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['service_id', 'file_url', 'media_type', 'is_primary', 'sort_order'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
