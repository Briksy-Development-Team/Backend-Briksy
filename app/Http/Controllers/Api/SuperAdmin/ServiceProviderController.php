<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\ServiceProviderIndexRequest;
use App\Http\Resources\SuperAdmin\ServiceProviderResource;
use App\Models\Organization;
use App\Models\SoleTraderProfile;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ServiceProviderController extends Controller
{
    public function index(ServiceProviderIndexRequest $request): JsonResponse
    {
        $providers = DB::query()->fromSub($this->providerBaseQuery(), 'service_providers');

        if ($search = $request->search()) {
            $providers->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('trading_name', 'like', "%{$search}%");
            });
        }

        if ($providerType = $request->input('filter.provider_type')) {
            $providers->where('provider_type', $providerType);
        }

        if ($request->has('filter.is_verified')) {
            $providers->where('is_verified', $request->boolean('filter.is_verified') ? 1 : 0);
        }

        if ($typeId = $request->input('filter.type_id')) {
            $providers->where('type_id', $typeId);
        }

        $allowedSorts = $request->allowedSorts();
        $sortColumn = $allowedSorts[$request->sort()] ?? 'created_at';
        $sortDirection = $request->direction() === 'asc' ? 'asc' : 'desc';

        $paginator = $providers->orderBy($sortColumn, $sortDirection)
            ->paginate($request->perPage())
            ->withQueryString();

        return $this->paginated(
            ServiceProviderResource::collection($paginator),
            $paginator,
            'Service providers retrieved successfully.'
        );
    }

    public function show(string $providerType, string $providerId): JsonResponse
    {
        if ($providerType === 'organization') {
            $provider = Organization::query()
                ->with('organizationType')
                ->findOrFail($providerId);

            return $this->success(
                new ServiceProviderResource($provider),
                'Service provider retrieved successfully.'
            );
        }

        if ($providerType === 'sole_trader') {
            $provider = SoleTraderProfile::query()
                ->with(['user', 'organization.organizationType'])
                ->findOrFail($providerId);

            return $this->success(
                new ServiceProviderResource($provider),
                'Service provider retrieved successfully.'
            );
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid provider type. Allowed values are organization or sole_trader.',
        ], 422);
    }

    private function providerBaseQuery(): Builder
    {
        $organizations = DB::table('organizations')
            ->leftJoin('organization_types', 'organization_types.id', '=', 'organizations.type_id')
            ->whereNull('organizations.deleted_at')
            ->selectRaw('
                organizations.id as id,
                null as user_id,
                organizations.id as organization_id,
                organizations.name as name,
                organizations.slug as slug,
                organizations.contact_email as email,
                organizations.contact_phone as phone,
                organizations.is_verified as is_verified,
                organizations.type_id as type_id,
                organization_types.name as type_name,
                organization_types.slug as type_slug,
                null as trading_name,
                null as primary_service_postcode,
                \'organization\' as provider_type,
                organizations.created_at as created_at
            ');

        $soleTraders = DB::table('sole_trader_profiles')
            ->join('users', 'users.id', '=', 'sole_trader_profiles.user_id')
            ->leftJoin('organizations', 'organizations.id', '=', 'sole_trader_profiles.organization_id')
            ->leftJoin('organization_types', 'organization_types.id', '=', 'organizations.type_id')
            ->whereNull('sole_trader_profiles.deleted_at')
            ->whereNull('users.deleted_at')
            ->selectRaw('
                sole_trader_profiles.id as id,
                users.id as user_id,
                sole_trader_profiles.organization_id as organization_id,
                coalesce(sole_trader_profiles.trading_name, users.display_name, users.name) as name,
                organizations.slug as slug,
                users.email as email,
                users.mobile_number as phone,
                users.id_verified as is_verified,
                organizations.type_id as type_id,
                organization_types.name as type_name,
                organization_types.slug as type_slug,
                sole_trader_profiles.trading_name as trading_name,
                sole_trader_profiles.primary_service_postcode as primary_service_postcode,
                \'sole_trader\' as provider_type,
                sole_trader_profiles.created_at as created_at
            ');

        return $organizations->unionAll($soleTraders);
    }
}
