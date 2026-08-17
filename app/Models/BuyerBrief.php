<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BuyerBrief extends Model
{
    use HasUuids, SoftDeletes;
    protected $keyType = 'string'; public $incrementing = false;
    protected $fillable = ['organization_id','created_by','client_name','client_email','status','budget_min','budget_max','preferred_locations','preferences','notes'];
    protected function casts(): array { return ['preferred_locations' => 'array', 'preferences' => 'array']; }
}
