<?php

namespace App\Http\Controllers\Api;

use App\Models\PropertyType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PropertyType::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        return $this->success(
            $query->get(['id', 'name', 'slug', 'category'])->values()->all(),
            'Property types retrieved successfully.'
        );
    }
}
