<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitorLog extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'visitor_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'viewer_id',
        'organization_id',
        'property_listing_id',
        'ip_address',
    ];
}
