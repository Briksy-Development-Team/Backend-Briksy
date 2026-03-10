<?php

namespace App\Models\Admin;

use App\Models\Admin\OrganizationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Organization extends Model
{
    use SoftDeletes;
    protected $keyType = 'string';
    public $incrementing = false;
     
    protected $fillable = [
        'name',
        'address',
        'contact_email',
        'contact_phone',
        'abn',
        'plan_id',
        'type_id',
        'ranking_priority',
        'avg_org_rating',
        'stripe_customer_id',
        'is_verified',
        'slug',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function inquiries()
    {
        return $this->hasMany(Inquiry::class);
    }

    public function organizationType()
    {
        return $this->belongsTo(OrganizationType::class, 'type_id');
    }
}
