<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\PropertyListingIndexRequest;
use App\Http\Resources\Seeker\PropertyListingResource;
use App\Models\PropertyListing;
use App\Models\VisitorLog;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Support\Facades\Schema;

class PropertySearchController extends Controller
{
    public function index(PropertyListingIndexRequest $request)
    {
        $query = PropertyListing::query()
            ->visibleToSeekers()
            ->with(['organization.organizationType', 'organization.currentSubscription.plan', 'propertyType', 'media', 'features', 'offers' => fn ($offerQuery) => $offerQuery->where('is_active', true)->orderBy('sort_order')]);

        if ($viewerId = $request->user('sanctum')?->id) {
            $query->withExists(['favorites as is_favourite' => fn ($favoriteQuery) => $favoriteQuery->where('user_id', $viewerId)]);
        }

        ApiQueryBuilder::applySearch($query, $request->search(), ['title', 'description', 'suburb', 'postcode']);
        ApiQueryBuilder::applyExactFilters($query, [
            'suburb' => $request->input('suburb'),
            'postcode' => $request->input('postcode'),
        ]);

        $purpose = strtoupper((string) $request->input('purpose'));
        if (in_array($purpose, ['SELL', 'RENT'], true)) {
            $query->whereIn('listing_purpose', [$purpose, 'BOTH']);
        } elseif ($purpose === 'BOTH') {
            $query->where('listing_purpose', 'BOTH');
        }

        if ($request->filled('category')) {
            $query->whereHas('propertyType', fn ($typeQuery) => $typeQuery->where('category', $request->string('category')->toString()));
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        $this->applyPropertyAttributeFilters($query, $request);

        if ($request->filled('organization_slug')) {
            $query->whereHas('organization', fn ($organizationQuery) => $organizationQuery->where('slug', $request->string('organization_slug')->toString()));
        }

        if ($request->filled('organization_type')) {
            $query->whereHas('organization.organizationType', fn ($typeQuery) => $typeQuery->where('slug', $request->string('organization_type')->toString()));
        }

        if ($request->filled('service_slug')) {
            $query->whereHas('organization.services', fn ($serviceQuery) => $serviceQuery->where('services.slug', $request->string('service_slug')->toString()));
        }

        if ($request->boolean('verified_only')) {
            $query->whereHas('organization', fn ($organizationQuery) => $organizationQuery->where('is_verified', true));
        }

        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $properties = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            PropertyListingResource::collection($properties),
            $properties,
            'Property listings retrieved successfully.'
        );
    }

    public function show(PropertyListing $propertyListing)
    {
        $query = PropertyListing::query()
            ->visibleToSeekers()
            ->with(['organization.organizationType', 'organization.currentSubscription.plan', 'propertyType', 'media', 'features', 'offers' => fn ($offerQuery) => $offerQuery->where('is_active', true)->orderBy('sort_order')]);

        if ($viewerId = request()->user('sanctum')?->id) {
            $query->withExists(['favorites as is_favourite' => fn ($favoriteQuery) => $favoriteQuery->where('user_id', $viewerId)]);
        }

        $property = $query->findOrFail($propertyListing->id);

        $this->recordVisit($property, request());

        return $this->success(
            new PropertyListingResource($property),
            'Property listing retrieved successfully.'
        );
    }

    private function applyPropertyAttributeFilters(\Illuminate\Database\Eloquent\Builder $query, PropertyListingIndexRequest $request): void
    {
        $minimums = [
            'bedrooms' => ['studio', '1', '2', '3', '4', '5_plus'],
            'bathrooms' => ['1', '2', '3_plus'],
            'car_spaces' => ['1', '2', '3_plus'],
        ];

        foreach ($minimums as $input => $options) {
            if (! $request->filled($input) || (int) $request->input($input) < 1) {
                continue;
            }

            $minimum = (int) $request->input($input);
            $column = $input === 'bedrooms' ? 'bedroom_option' : ($input === 'bathrooms' ? 'bathroom_option' : 'car_space_option');
            $query->whereIn($column, array_values(array_filter($options, function (string $option) use ($minimum): bool {
                $value = $option === 'studio' ? 0 : ($option === '5_plus' || $option === '3_plus' ? 5 : (int) $option);
                return $value >= $minimum;
            })));
        }

        if ($request->filled('min_land_size')) {
            $query->where('land_area_sqm', '>=', $request->input('min_land_size'));
        }
        if ($request->filled('max_land_size')) {
            $query->where('land_area_sqm', '<=', $request->input('max_land_size'));
        }

        foreach ($request->input('features', []) as $featureSlug) {
            $query->whereHas('features', fn ($featureQuery) => $featureQuery->where('property_features.slug', $featureSlug));
        }
    }

    private function recordVisit(PropertyListing $property, \Illuminate\Http\Request $request): void
    {
        if (!Schema::hasTable('visitor_logs')) {
            return;
        }

        VisitorLog::query()->create([
            'viewer_id' => $request->user()?->id,
            'organization_id' => $property->org_id,
            'property_listing_id' => $property->id,
            'ip_address' => $request->ip(),
        ]);
    }
}
