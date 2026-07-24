<?php

namespace App\Services;

use App\Http\Requests\Api\SuperAdmin\PropertyMapIndexRequest;
use App\Models\PropertyListing;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class PropertyMapService
{
    public function list(PropertyMapIndexRequest $request): Collection
    {
        $query = $this->baseQuery();

        $this->applySearch($query, $request->search());
        $this->applyFilters($query, $request);

        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        return $query->get();
    }

    private function baseQuery(): Builder
    {
        return PropertyListing::query()
            ->select([
                'id',
                'org_id',
                'generated_id',
                'property_type_id',
                'title',
                'latitude',
                'longitude',
                'status',
                'location_verified',
                'address',
                'address_line_1',
                'full_address',
                'formatted_address',
                'suburb',
                'state',
                'country',
                'created_at',
                'updated_at',
            ])
            ->with([
                'organization:id,name,slug,is_verified',
                'propertyType:id,name,slug',
                'media:id,property_listing_id,file_url,media_type,is_primary,sort_order',
            ])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [-90, 90])
            ->whereBetween('longitude', [-180, 180]);
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        if (blank($search)) {
            return;
        }

        $term = '%' . trim($search) . '%';

        $query->where(function (Builder $builder) use ($term): void {
            $builder
                ->where('generated_id', 'like', $term)
                ->orWhere('title', 'like', $term)
                ->orWhere('suburb', 'like', $term)
                ->orWhereHas('organization', function (Builder $organizationQuery) use ($term): void {
                    $organizationQuery
                        ->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term);
                });
        });
    }

    private function applyFilters(Builder $query, PropertyMapIndexRequest $request): void
    {
        if ($request->filled('filter.status')) {
            $query->where('status', $request->string('filter.status')->toString());
        }

        if ($request->filled('filter.organization_id')) {
            $query->where('org_id', $request->string('filter.organization_id')->toString());
        } elseif ($request->filled('filter.organization')) {
            $organizationFilter = $request->string('filter.organization')->toString();
            $query->where(function (Builder $builder) use ($organizationFilter): void {
                if (Str::isUuid($organizationFilter)) {
                    $builder->where('org_id', $organizationFilter);
                    return;
                }

                $builder->whereHas('organization', function (Builder $organizationQuery) use ($organizationFilter): void {
                    $organizationQuery
                        ->where('name', 'like', '%' . $organizationFilter . '%')
                        ->orWhere('slug', 'like', '%' . $organizationFilter . '%');
                });
            });
        }

        if ($request->has('filter.verified')) {
            $query->where('location_verified', $request->boolean('filter.verified'));
        }

        if ($request->filled('filter.country')) {
            $query->where('country', $request->string('filter.country')->toString());
        }

        if ($request->filled('filter.state')) {
            $query->where('state', $request->string('filter.state')->toString());
        }

        if ($request->filled('filter.city')) {
            $query->where('suburb', $request->string('filter.city')->toString());
        }

        if ($request->filled('filter.property_type_id')) {
            $query->where('property_type_id', $request->string('filter.property_type_id')->toString());
        } elseif ($request->filled('filter.property_type')) {
            $propertyTypeFilter = $request->string('filter.property_type')->toString();
            $query->where(function (Builder $builder) use ($propertyTypeFilter): void {
                if (Str::isUuid($propertyTypeFilter)) {
                    $builder->where('property_type_id', $propertyTypeFilter);
                    return;
                }

                $builder->whereHas('propertyType', function (Builder $propertyTypeQuery) use ($propertyTypeFilter): void {
                    $propertyTypeQuery
                        ->where('name', 'like', '%' . $propertyTypeFilter . '%')
                        ->orWhere('slug', 'like', '%' . $propertyTypeFilter . '%');
                });
            });
        }
    }
}
