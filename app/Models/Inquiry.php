<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inquiry extends Model
{
    use HasUuids, SoftDeletes;
    
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'property_listing_id',
        'message',
        'subject',
        'organization_id',
        'staff_id',
        'seeker_name',
        'seeker_email',
        'seeker_phone',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function propertyListing(): BelongsTo
    {
        return $this->belongsTo(PropertyListing::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
