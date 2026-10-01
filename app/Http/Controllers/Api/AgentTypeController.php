<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\SuperAdmin\OrganizationTypeResource;
use App\Models\OrganizationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $types = OrganizationType::query()
            ->where('module', 'Agents')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->orderBy('name')
            ->get();

        return $this->success(
            OrganizationTypeResource::collection($types)->resolve(),
            'Agent types retrieved successfully.'
        );
    }
}
