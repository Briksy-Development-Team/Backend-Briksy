<?php

namespace App\Models\Admin;

use App\Models\BusinessData\PropertyListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inquiry extends Model
{
    use SoftDeletes;
    
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'property_listing_id',
        'message',
        'subject',
        'organization_id',
        'staff_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function propertyListing()
    {
        return $this->belongsTo(PropertyListing::class);
    }
    public function staff()
    {
        return $this->belongsTo(User::class);
    }
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

}
