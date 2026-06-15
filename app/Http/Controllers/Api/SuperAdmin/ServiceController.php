<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Models\ServiceGroup;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ServiceGroup::query()
            ->with(['organizationType'])
            ->withCount(['services', 'organizations']);

        ApiQueryBuilder::applySearch($query, $request->string('search')->toString(), ['name', 'slug', 'description']);

        if ($request->filled('filter.type_slug')) {
            $typeSlug = $request->string('filter.type_slug')->toString();
            $query->whereHas('organizationType', fn ($typeQuery) => $typeQuery->where('slug', $typeSlug));
        }

        ApiQueryBuilder::applySort(
            $query,
            $request->string('sort')->toString(),
            $request->string('direction')->toString() ?: 'asc',
            [
                'id' => 'id',
                'name' => 'name',
                'slug' => 'slug',
                'created_at' => 'created_at',
                'services_count' => 'services_count',
                'organizations_count' => 'organizations_count',
            ],
            'created_at'
        );

        $groups = $query->paginate($request->integer('per_page', 10))->withQueryString();

        $data = $groups->getCollection()->map(static function (ServiceGroup $group): array {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'description' => $group->description,
                'organization_type' => $group->organizationType ? [
                    'id' => $group->organizationType->id,
                    'name' => $group->organizationType->name,
                    'slug' => $group->organizationType->slug,
                ] : null,
                'services_count' => $group->services_count,
                'organization_count' => $group->organizations_count,
                'created_at' => $group->created_at?->toISOString(),
                'updated_at' => $group->updated_at?->toISOString(),
            ];
        })->all();

        return $this->paginated(
            $data,
            $groups,
            'Services retrieved successfully.'
        );
    }
}
