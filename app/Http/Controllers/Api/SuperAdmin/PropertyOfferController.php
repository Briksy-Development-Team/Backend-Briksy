<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Concerns\AppliesOrganizationScope;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\PropertyOfferIndexRequest;
use App\Http\Requests\Api\SuperAdmin\PropertyOfferStoreRequest;
use App\Http\Requests\Api\SuperAdmin\PropertyOfferUpdateRequest;
use App\Http\Resources\PropertyOfferResource;
use App\Models\PropertyListing;
use App\Models\PropertyOffer;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyOfferController extends Controller
{
    use AppliesOrganizationScope;

    public function index(PropertyOfferIndexRequest $request): JsonResponse
    {
        $query = $this->scopedQuery(PropertyOffer::query()->with(['propertyListing.organization', 'creator']), $request);
        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());
        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'sort_order');

        $items = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(PropertyOfferResource::collection($items)->resolve(), $items, 'Property offers retrieved successfully.');
    }

    public function show(Request $request, string $propertyOffer): JsonResponse
    {
        $model = $this->findOffer($request, $propertyOffer);

        return $this->success(new PropertyOfferResource($model->load(['propertyListing.organization', 'creator'])), 'Property offer retrieved successfully.');
    }

    public function store(PropertyOfferStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $propertyListing = $this->resolvePropertyListing($request, $validated['property_listing_id']);

        $offer = PropertyOffer::query()->create([
            'organization_id' => $propertyListing->org_id,
            'property_listing_id' => $propertyListing->id,
            'created_by' => $request->user()?->id,
            'title' => $validated['title'],
            'tag_label' => $validated['tag_label'] ?? 'BRIKSY EXCLUSIVE',
            'summary' => $validated['summary'] ?? null,
            'description' => $validated['description'] ?? null,
            'highlights' => $validated['highlights'] ?? [],
            'terms' => $validated['terms'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return $this->created(new PropertyOfferResource($offer->load(['propertyListing.organization', 'creator'])), 'Property offer created successfully.');
    }

    public function update(PropertyOfferUpdateRequest $request, string $propertyOffer): JsonResponse
    {
        $model = $this->findOffer($request, $propertyOffer);
        $validated = $request->validated();

        if (isset($validated['property_listing_id'])) {
            $propertyListing = $this->resolvePropertyListing($request, $validated['property_listing_id']);
            $validated['organization_id'] = $propertyListing->org_id;
        } else {
            $validated['organization_id'] = $model->propertyListing?->org_id ?? $model->organization_id;
        }

        $model->fill($validated);
        $model->save();

        return $this->success(new PropertyOfferResource($model->fresh()->load(['propertyListing.organization', 'creator'])), 'Property offer updated successfully.');
    }

    public function destroy(Request $request, string $propertyOffer): JsonResponse
    {
        $model = $this->findOffer($request, $propertyOffer);
        $model->delete();

        return $this->success([], 'Property offer deleted successfully.');
    }

    public function toggle(Request $request, string $propertyOffer): JsonResponse
    {
        $model = $this->findOffer($request, $propertyOffer);
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $model->update(['is_active' => $validated['is_active']]);

        return $this->success(new PropertyOfferResource($model->fresh()->load(['propertyListing.organization', 'creator'])), 'Property offer status updated successfully.');
    }

    private function findOffer(Request $request, string $id): PropertyOffer
    {
        return $this->scopedQuery(PropertyOffer::query(), $request)
            ->with(['propertyListing.organization', 'creator'])
            ->where(function ($query) use ($id): void {
                $query->whereKey($id)
                    ->orWhere('title', $id);
            })
            ->firstOrFail();
    }

    private function resolvePropertyListing(Request $request, string $propertyListingId): PropertyListing
    {
        $query = PropertyListing::query();
        $organizationId = $this->organizationId($request);

        if ($organizationId) {
            $query->where('org_id', $organizationId);
        }

        return $query->findOrFail($propertyListingId);
    }
}
