<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Concerns\AppliesOrganizationScope;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\ServiceOfferIndexRequest;
use App\Http\Requests\Api\SuperAdmin\ServiceOfferStoreRequest;
use App\Http\Requests\Api\SuperAdmin\ServiceOfferUpdateRequest;
use App\Http\Resources\ServiceOfferResource;
use App\Models\Organization;
use App\Models\Service;
use App\Models\ServiceOffer;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceOfferController extends Controller
{
    use AppliesOrganizationScope;

    public function index(ServiceOfferIndexRequest $request): JsonResponse
    {
        $query = $this->scopedQuery(ServiceOffer::query()->with(['service.organization', 'creator']), $request);
        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());
        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'sort_order');
        $items = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(ServiceOfferResource::collection($items)->resolve(), $items, 'Service offers retrieved successfully.');
    }

    public function show(Request $request, string $serviceOffer): JsonResponse
    {
        $model = $this->findOffer($request, $serviceOffer);
        return $this->success(new ServiceOfferResource($model->load(['service.organization', 'creator'])), 'Service offer retrieved successfully.');
    }

    public function store(ServiceOfferStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $service = $this->resolveService($request, $validated['service_id']);
        $organization = $service->organization()->with(['currentSubscription.plan'])->firstOrFail();
        $this->assertPromoOfferAllowed($organization, true);

        $offer = ServiceOffer::query()->create([
            'organization_id' => $service->organization_id,
            'service_id' => $service->id,
            'created_by' => $request->user()?->id,
            ...$this->offerPayload($validated),
        ]);

        return $this->created(new ServiceOfferResource($offer->load(['service.organization', 'creator'])), 'Service offer created successfully.');
    }

    public function update(ServiceOfferUpdateRequest $request, string $serviceOffer): JsonResponse
    {
        $model = $this->findOffer($request, $serviceOffer);
        $validated = $request->validated();
        $service = isset($validated['service_id']) ? $this->resolveService($request, $validated['service_id']) : $model->service;
        $organization = $service->organization()->with(['currentSubscription.plan'])->firstOrFail();
        $requestedActive = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : (bool) $model->is_active;
        $this->assertPromoOfferAllowed($organization, $requestedActive, $model);

        $model->fill($this->offerPayload($validated, true));
        $model->service_id = $service->id;
        $model->organization_id = $service->organization_id;
        $model->save();

        return $this->success(new ServiceOfferResource($model->fresh()->load(['service.organization', 'creator'])), 'Service offer updated successfully.');
    }

    public function destroy(Request $request, string $serviceOffer): JsonResponse
    {
        $this->findOffer($request, $serviceOffer)->delete();
        return $this->success([], 'Service offer deleted successfully.');
    }

    public function toggle(Request $request, string $serviceOffer): JsonResponse
    {
        $model = $this->findOffer($request, $serviceOffer);
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $organization = $model->service()->with(['currentSubscription.plan'])->firstOrFail()->organization;
        $this->assertPromoOfferAllowed($organization, (bool) $validated['is_active'], $model);
        $model->update(['is_active' => $validated['is_active']]);

        return $this->success(new ServiceOfferResource($model->fresh()->load(['service.organization', 'creator'])), 'Service offer status updated successfully.');
    }

    private function offerPayload(array $validated, bool $updating = false): array
    {
        $fields = ['title', 'tag_label', 'summary', 'description', 'highlights', 'terms', 'starts_at', 'ends_at', 'is_active', 'sort_order'];
        $payload = [];
        foreach ($fields as $field) {
            if (!$updating || array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field] ?? ($field === 'highlights' ? [] : ($field === 'is_active' ? true : ($field === 'sort_order' ? 0 : null)));
            }
        }
        return $payload;
    }

    private function findOffer(Request $request, string $id): ServiceOffer
    {
        return $this->scopedQuery(ServiceOffer::query(), $request)
            ->with(['service.organization', 'creator'])
            ->where(fn ($query) => $query->whereKey($id)->orWhere('title', $id))
            ->firstOrFail();
    }

    private function resolveService(Request $request, string $serviceId): Service
    {
        $query = Service::query()->with('organization');
        if ($organizationId = $this->organizationId($request)) {
            $query->where('organization_id', $organizationId);
        }
        return $query->findOrFail($serviceId);
    }

    private function assertPromoOfferAllowed(Organization $organization, bool $activating, ?ServiceOffer $current = null): void
    {
        $user = request()->user();
        if ($user?->isSuperAdmin() || $user?->isGlobalStaff()) {
            return;
        }

        $plan = $organization->plan;
        $feature = collect($plan?->features ?? [])->first(fn (array $item): bool => strcasecmp((string) ($item['name'] ?? ''), 'Promo Offers') === 0);
        if (!($feature['enabled'] ?? false)) {
            $this->planError('PLAN_PROMO_OFFERS_NOT_INCLUDED', 'Promotional offers are not included in your current plan.');
        }

        $limit = array_key_exists('value', $feature ?? []) && $feature['value'] !== null ? (int) $feature['value'] : null;
        if ($activating && $limit !== null) {
            $active = ServiceOffer::query()->where('organization_id', $organization->id)->where('is_active', true)->when($current, fn ($query) => $query->where($current->getKeyName(), '!=', $current->getKey()))->count();
            if ($active >= $limit) {
                $this->planError('PLAN_PROMO_OFFERS_LIMIT_REACHED', sprintf('Your plan allows up to %d promotional offers.', $limit));
            }
        }
    }

    private function planError(string $code, string $message): never
    {
        throw new HttpResponseException(response()->json(['success' => false, 'code' => $code, 'message' => $message], 422));
    }
}
