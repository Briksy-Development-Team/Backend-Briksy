<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\SubscriptionIndexRequest;
use App\Http\Resources\SuperAdmin\SubscriptionResource;
use App\Models\Subscription;
use App\Models\Invoice;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionController extends Controller
{
    public function index(SubscriptionIndexRequest $request): JsonResponse
    {
        $query = Subscription::query()->with(['organization', 'plan', 'addons.addon']);
        if ($request->search()) {
            $search = $request->search();
            $query->where(function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                foreach (['stripe_customer_id', 'stripe_subscription_id', 'stripe_checkout_session_id', 'latest_invoice_id'] as $column) {
                    $builder->orWhere($column, 'like', $like);
                }
                $builder->orWhereHas('organization', fn (Builder $organization) => $organization->where('name', 'like', $like));
                $builder->orWhereHas('plan', fn (Builder $plan) => $plan->where('name', 'like', $like));
                $invoiceOrganizationIds = Invoice::query()
                    ->where('invoice_number', 'like', $like)
                    ->pluck('organization_id');
                if ($invoiceOrganizationIds->isNotEmpty()) {
                    $builder->orWhereIn('organization_id', $invoiceOrganizationIds);
                }
            });
        }
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
