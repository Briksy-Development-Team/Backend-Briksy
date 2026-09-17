<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\StoreInquiryRequest;
use App\Http\Resources\Seeker\InquiryResource;
use App\Models\ActivityLog;
use App\Models\Inquiry;
use App\Models\Organization;
use App\Models\PropertyListing;
use App\Models\User;
use App\Services\DynamicIdGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class InquiryController extends Controller
{
    public function index(): JsonResponse
    {
        $user = request()->user();

        $inquiries = Inquiry::query()
            ->where('user_id', $user->id)
            ->with([
                'propertyListing.organization.organizationType',
                'organization.organizationType',
                'staff',
            ])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return $this->paginated(
            InquiryResource::collection($inquiries),
            $inquiries,
            'Inquiries retrieved successfully.'
        );
    }

    public function store(StoreInquiryRequest $request): JsonResponse
    {
        $authUser = $request->user();
        $organization = Organization::query()->findOrFail($request->input('organization_id'));

        if ($propertyId = $request->input('property_listing_id')) {
            abort_unless(PropertyListing::query()->whereKey($propertyId)->where('org_id', $organization->id)->exists(), 422, 'The selected property does not belong to this organisation.');
        }

        $staff = null;
        if ($staffId = $request->input('staff_id')) {
            $staff = User::query()->whereKey($staffId)->where('organization_id', $organization->id)->firstOrFail();
        }
        $referenceNo = app(DynamicIdGeneratorService::class)->generate('inquiries');
        $leadSource = $request->input('lead_source')
            ?? ($request->filled('property_listing_id') ? 'property_listing' : 'direct');
        $inquiryData = [
            'reference_no' => $referenceNo,
            'organization_id' => $request->input('organization_id'),
            'property_listing_id' => $request->input('property_listing_id'),
            'staff_id' => $request->input('staff_id'),
            'user_id' => $authUser?->id ?? $request->input('user_id'),
            'subject' => $request->input('subject'),
            'message' => $request->input('message'),
            'seeker_name' => $request->input('seeker_name') ?? $authUser?->name,
            'seeker_email' => $request->input('seeker_email') ?? $authUser?->email,
            'seeker_phone' => $request->input('seeker_phone'),
            'status' => 'new',
        ];

        if (Schema::hasColumn('inquiries', 'lead_source')) {
            $inquiryData['lead_source'] = $leadSource;
        }

        $inquiry = Inquiry::query()->create($inquiryData);

        $recipient = $staff?->email ?: $organization->contact_email;
        if ($recipient) {
            Mail::html(nl2br(e("{$inquiry->seeker_name} ({$inquiry->seeker_email}) sent an enquiry.\n\n{$inquiry->message}")), function ($message) use ($recipient, $inquiry): void {
                $message->to($recipient)->subject($inquiry->subject);
            });
        }

        if ($authUser && Schema::hasTable('activity_logs')) {
            ActivityLog::query()->create([
                'causer_id' => $authUser->id,
                'organization_id' => $organization->id,
                'user_id' => $authUser?->id,
                'user_name' => $authUser?->name ?? $inquiry->seeker_name,
                'user_email' => $authUser?->email ?? $inquiry->seeker_email,
                'user_role' => $authUser?->roles?->first()?->name,
                'action' => 'inquiry.created',
                'module' => 'inquiries',
                'description' => "Inquiry {$inquiry->reference_no} submitted.",
                'method' => 'POST',
                'route' => '/api/seeker/inquiries',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => ['inquiry_id' => $inquiry->id, 'recipient' => $recipient],
            ]);
        }

        return $this->created(
            new InquiryResource($inquiry),
            'Inquiry created successfully.'
        );
    }

    public function show(Inquiry $inquiry): JsonResponse
    {
        $user = request()->user();

        $inquiry->loadMissing([
            'propertyListing.organization.organizationType',
            'organization.organizationType',
            'staff',
        ]);

        if ($inquiry->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this inquiry.',
            ], 403);
        }

        return $this->success(
            new InquiryResource($inquiry),
            'Inquiry retrieved successfully.'
        );
    }
}
