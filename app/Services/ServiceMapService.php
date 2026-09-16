<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ServiceMapService
{
    public function list(Request $request): Collection
    {
        $query = $this->baseQuery();

        if (!$request->user()?->isSuperAdmin() && !$request->user()?->isGlobalStaff()) {
            $organizationId = $request->user()?->organization_id;

            if ($organizationId) {
                $query->where('organization_id', $organizationId);
            }
        }

        return $query
            ->where(function ($serviceQuery): void {
                $serviceQuery
                    ->whereNotNull('service_area_geometry')
                    ->orWhere(function ($areaQuery): void {
                        $areaQuery->whereNotNull('service_area')->whereRaw("TRIM(service_area) <> ''");
                    });
            })
            ->get();
    }

    private function baseQuery(): Builder
    {
        return Service::query()
            ->select([
                'id',
                'organization_id',
                'type_id',
                'generated_id',
                'name',
                'title',
                'category',
                'slug',
                'service_area',
                'service_area_geometry',
                'rate_from',
                'rate_to',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->with([
                'organization:id,name,slug,business_type',
                'organizationType:id,name,slug',
            ]);
    }
}
