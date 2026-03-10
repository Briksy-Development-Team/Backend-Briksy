<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\StoreInquiryRequest;
use App\Http\Resources\Seeker\InquiryResource;
use App\Models\Inquiry;

class InquiryController extends Controller
{
    public function store(StoreInquiryRequest $request)
    {
        $inquiry = Inquiry::create([
            'organization_id' => $request->input('organization_id'),
            'property_listing_id' => $request->input('property_listing_id'),
            'staff_id' => $request->input('staff_id'),
            'user_id' => $request->input('user_id'),
            'subject' => $request->input('subject'),
            'message' => $request->input('message'),
            'seeker_name' => $request->input('seeker_name'),
            'seeker_email' => $request->input('seeker_email'),
            'seeker_phone' => $request->input('seeker_phone'),
            'status' => 'new',
        ]);

        $inquiry->loadMissing(['organization', 'propertyListing']);

        return $this->created(
            new InquiryResource($inquiry),
            'Inquiry created successfully.'
        );
    }
}
