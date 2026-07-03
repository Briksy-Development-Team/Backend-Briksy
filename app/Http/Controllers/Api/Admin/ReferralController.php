<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Admin\AdminOrganizationResource;
use App\Models\Organization;
use App\Services\ReferralService;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referralService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $this->ensureReferralCode($organization);

        $query = Organization::query()
            ->where('referred_by_organization_id', $organization->id)
            ->with('organizationType');

        ApiQueryBuilder::applySearch($query, $request->string('search')->toString(), ['name', 'contact_email', 'referral_code']);
        ApiQueryBuilder::applySort($query, $request->input('sort'), $request->input('direction', 'desc'), [
            'created_at' => 'created_at',
            'name' => 'name',
            'contact_email' => 'contact_email',
        ], 'created_at');

        $referredOrganizations = $query->paginate(ApiQueryBuilder::normalizePerPage($request->integer('per_page'), 10, 100))->withQueryString();

        return $this->success([
            'organization' => new AdminOrganizationResource($organization->fresh()),
            'referral_code' => $organization->referral_code,
            'referral_link' => $this->referralService->referralLink($organization),
            'total_referrals' => $organization->referredOrganizations()->count(),
            'recent_referrals' => AdminOrganizationResource::collection($referredOrganizations)->resolve(),
            'pagination' => [
                'current_page' => $referredOrganizations->currentPage(),
                'per_page' => $referredOrganizations->perPage(),
                'total' => $referredOrganizations->total(),
                'last_page' => $referredOrganizations->lastPage(),
            ],
        ], 'Referral dashboard retrieved successfully.');
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->user()?->organization;
        abort_unless($organization, 403, 'Admin account is not assigned to an organization.');

        return $organization;
    }

    private function ensureReferralCode(Organization $organization): void
    {
        if ($organization->referral_code) {
            return;
        }

        $organization->forceFill([
            'referral_code' => $this->referralService->generateCode(),
        ])->save();
    }
}
