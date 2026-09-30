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
        $query = BuyerBrief::where('organization_id', $request->user()->organization_id);

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('client_name', 'like', "%{$search}%")
                    ->orWhere('client_email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        $sortable = ['client_name', 'client_email', 'status', 'budget_min', 'created_at'];
        $sort = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $items = $query->orderBy($sort, $direction)->paginate($request->integer('per_page', 10));
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

    public function show(Request $request, string $id)
    {
        return BuyerBrief::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $brief = BuyerBrief::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);
        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:150'],
            'client_email' => ['nullable', 'email'],
            'status' => ['nullable', 'string', 'max:30'],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'min:0'],
            'preferred_locations' => ['nullable', 'array'],
            'preferences' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ]);
        $brief->update($data);

        return $brief->fresh();
    }

    public function destroy(Request $request, string $id)
    {
        $brief = BuyerBrief::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);
        $brief->delete();

        return response()->json(['success' => true, 'message' => 'Buyer brief deleted successfully.']);
    }
}
