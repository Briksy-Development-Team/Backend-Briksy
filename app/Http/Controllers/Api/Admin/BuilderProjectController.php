<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Models\BuilderProject;
use App\Support\Business\PlanCapabilityResolver;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class BuilderProjectController extends Controller
{
    public function __construct(private readonly PlanCapabilityResolver $planCapabilities) {}
    public function index(Request $request)
    {
        $items = BuilderProject::where('organization_id', $request->user()->organization_id)->latest()->paginate($request->integer('items_per_page', 20));
        return $this->paginated($items, $items, 'Builder projects retrieved successfully.');
    }

    public function store(Request $request)
    {
        $entitlements = $this->planCapabilities->resolved($request->user());
        $limit = $entitlements['limits']['projects'] ?? 0;
        $used = BuilderProject::where('organization_id', $request->user()->organization_id)->count();
        if (!$entitlements['active']) {
            throw new HttpResponseException(response()->json(['success' => false, 'code' => 'SUBSCRIPTION_REQUIRED', 'message' => 'An active subscription is required.'], 402));
        }
        if ((int) $limit > 0 && $used >= (int) $limit) {
            throw new HttpResponseException(response()->json(['success' => false, 'code' => 'PLAN_PROJECT_LIMIT_REACHED', 'message' => sprintf('Your plan allows up to %d projects.', $limit)], 422));
        }
        $data = $request->validate(['name' => ['required','string','max:150'], 'project_type' => ['nullable','string','max:80'], 'status' => ['nullable','string','max:30'], 'description' => ['nullable','string','max:20000'], 'features' => ['nullable','array','max:30'], 'features.*' => ['required','string','max:80'], 'location' => ['nullable','string','max:150'], 'state' => ['nullable','string','max:10'], 'postcode' => ['nullable','string','max:10'], 'latitude' => ['nullable','numeric'], 'longitude' => ['nullable','numeric']]);
        $data['organization_id'] = $request->user()->organization_id; $data['created_by'] = $request->user()->id;
        return $this->created(BuilderProject::create($data), 'Builder project created successfully.');
    }
}
