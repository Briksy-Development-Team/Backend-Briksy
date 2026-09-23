<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Models\BuyerBrief;
use App\Support\Business\PlanCapabilityResolver;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class BuyerBriefController extends Controller
{
    public function __construct(private readonly PlanCapabilityResolver $planCapabilities) {}
    public function index(Request $request)
    {
        $items = BuyerBrief::where('organization_id', $request->user()->organization_id)->latest()->paginate($request->integer('items_per_page', 20));
        return $this->paginated($items, $items, 'Buyer briefs retrieved successfully.');
    }

    public function store(Request $request)
    {
        $entitlements = $this->planCapabilities->resolved($request->user());
        $limit = $entitlements['limits']['buyer_briefs'] ?? 0;
        $used = BuyerBrief::where('organization_id', $request->user()->organization_id)->count();
        if (!$entitlements['active']) {
            throw new HttpResponseException(response()->json(['success' => false, 'code' => 'SUBSCRIPTION_REQUIRED', 'message' => 'An active subscription is required.'], 402));
        }
        if ((int) $limit > 0 && $used >= (int) $limit) {
            throw new HttpResponseException(response()->json(['success' => false, 'code' => 'PLAN_BUYER_BRIEF_LIMIT_REACHED', 'message' => sprintf('Your plan allows up to %d buyer briefs.', $limit)], 422));
        }
        $data = $request->validate(['client_name' => ['required','string','max:150'], 'client_email' => ['nullable','email'], 'status' => ['nullable','string','max:30'], 'budget_min' => ['nullable','integer','min:0'], 'budget_max' => ['nullable','integer','min:0'], 'preferred_locations' => ['nullable','array'], 'preferences' => ['nullable','array'], 'notes' => ['nullable','string']]);
        $data['organization_id'] = $request->user()->organization_id; $data['created_by'] = $request->user()->id;
        return $this->created(BuyerBrief::create($data), 'Buyer brief created successfully.');
    }
}
