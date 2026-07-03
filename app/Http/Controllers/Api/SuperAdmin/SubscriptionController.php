<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\SubscriptionIndexRequest;
use App\Http\Resources\SuperAdmin\SubscriptionResource;
use App\Models\Subscription;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    public function index(SubscriptionIndexRequest $request): JsonResponse
    {
        $query = Subscription::query()->with(['organization', 'plan', 'addons.addon']);
        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());
        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $items = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            SubscriptionResource::collection($items)->resolve(),
            $items,
            'Subscriptions retrieved successfully.'
        );
    }
}
