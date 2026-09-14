<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Admin\AdminInquiryResource;
use App\Models\Inquiry;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Inquiry::query()
            ->with(['propertyListing.organization.organizationType', 'organization.organizationType']);

        $this->scopeToAdminOrganization($query, $request);
        $this->applySearch($query, (string) $request->query('search', ''));
        $this->applyFilters($query, $request);

        $sort = (string) $request->query('sort', 'created_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = [
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'status' => 'status',
            'subject' => 'subject',
            'display_id' => 'reference_no',
            'reference_no' => 'reference_no',
        ];

        $query->orderBy($allowedSorts[$sort] ?? 'created_at', $direction);

        $inquiries = $query
            ->paginate(ApiQueryBuilder::normalizePerPage((int) $request->query('per_page', 10)))
            ->withQueryString();

        return $this->paginated(
            AdminInquiryResource::collection($inquiries),
            $inquiries,
            'Inquiries retrieved successfully.'
        );
    }

    public function show(Request $request, Inquiry $inquiry): JsonResponse
    {
        $inquiry->load(['propertyListing.organization.organizationType', 'organization.organizationType']);

        abort_unless($this->canAccessInquiry($request, $inquiry), 403);

        return $this->success(
            new AdminInquiryResource($inquiry),
            'Inquiry retrieved successfully.'
        );
    }

    private function scopeToAdminOrganization(Builder $query, Request $request): void
    {
        if ($this->isSuperAdminRoute($request)) {
            return;
        }

        $organizationId = $request->user()?->organization_id;
        abort_unless($organizationId, 403);

        $query->where('organization_id', $organizationId);
    }

    private function canAccessInquiry(Request $request, Inquiry $inquiry): bool
    {
        if ($this->isSuperAdminRoute($request)) {
            return true;
        }

        return (string) $inquiry->organization_id === (string) $request->user()?->organization_id;
    }

    private function isSuperAdminRoute(Request $request): bool
    {
        return str_starts_with($request->path(), 'api/super-admin/');
    }

    private function applySearch(Builder $query, string $search): void
    {
        if (blank($search)) {
            return;
        }

        $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('reference_no', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%")
                ->orWhere('seeker_name', 'like', "%{$search}%")
                ->orWhere('seeker_email', 'like', "%{$search}%")
                ->orWhereHas('propertyListing', fn (Builder $property): Builder => $property->where('title', 'like', "%{$search}%"))
                ->orWhereHas('organization', fn (Builder $organization): Builder => $organization->where('name', 'like', "%{$search}%"));
        });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $filters = (array) $request->query('filter', []);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['lead_source'])) {
            $query->where('lead_source', $filters['lead_source']);
        }

        if (!empty($filters['property_listing_id'])) {
            $query->where('property_listing_id', $filters['property_listing_id']);
        }
    }
}
