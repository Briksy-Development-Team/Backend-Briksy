<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\SuperAdmin\OrganizationResource;
use App\Models\Organization;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Organization::query()
            ->with(['organizationType', 'referredByOrganization'])
            ->withCount('referredOrganizations');

        $request->validate(['filter.with_referrals' => ['nullable', 'boolean']]);
        $search = $request->string('search')->trim()->toString();
        if ($search !== '') {
            $query->where(function ($matches) use ($search): void {
                ApiQueryBuilder::applySearch($matches, $search, ['name', 'trading_name', 'contact_email', 'referral_code', 'slug']);
                $matches->orWhereHas('referredByOrganization', fn ($referrer) => $referrer->where('name', 'like', "%{$search}%"));
            });
        }

        $withReferrals = (clone $query)->whereHas('referredOrganizations');
        $totals = [
            'organizations' => $query->count(),
            'with_referrals' => $withReferrals->count(),
        ];
        if ($request->boolean('filter.with_referrals')) {
            $query = $withReferrals;
        }
        ApiQueryBuilder::applySort($query, $request->input('sort'), $request->input('direction', 'desc'), [
            'created_at' => 'created_at',
            'name' => 'name',
            'contact_email' => 'contact_email',
            'referred_organizations_count' => 'referred_organizations_count',
        ], 'created_at');
        $query->orderBy('id');

        $organizations = $query->paginate(ApiQueryBuilder::normalizePerPage($request->integer('per_page'), 10, 100))->withQueryString();

        return $this->success([
            'organizations' => OrganizationResource::collection($organizations)->resolve(),
            'totals' => $totals,
            'pagination' => [
                'current_page' => $organizations->currentPage(),
                'per_page' => $organizations->perPage(),
                'total' => $organizations->total(),
                'last_page' => $organizations->lastPage(),
            ],
        ], 'Referral programs retrieved successfully.');
    }
}
