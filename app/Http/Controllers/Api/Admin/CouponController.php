<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\SuperAdmin\CouponController as BaseCouponController;
use Illuminate\Http\Request;

class CouponController extends BaseCouponController
{
    // Coupon validation is organization-agnostic, so no override needed.
}
