<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\SuperAdmin\OrderController as BaseOrderController;

class OrderController extends BaseOrderController
{
    public function show(string $order): \Illuminate\Http\JsonResponse
    {
        return parent::show($order);
    }
}
