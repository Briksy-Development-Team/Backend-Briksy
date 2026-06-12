<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Models\Organization;
use App\Models\PropertyListing;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $propertySummary = DB::table('property_listings')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'Published' THEN 1 ELSE 0 END) as published")
            ->selectRaw("SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) as draft")
            ->selectRaw("SUM(CASE WHEN status = 'Archived' THEN 1 ELSE 0 END) as archived")
            ->first();

        $payload = [
            'total_companies' => Organization::query()->count(),
            'active_plans' => SubscriptionPlan::query()->where('is_active', true)->count(),
            'total_orders' => DB::table('subscriptions')->count(),
            'plan_requests' => 0,
            'property_summary' => [
                'total' => (int) ($propertySummary->total ?? 0),
                'published' => (int) ($propertySummary->published ?? 0),
                'draft' => (int) ($propertySummary->draft ?? 0),
                'archived' => (int) ($propertySummary->archived ?? 0),
            ],
            'recent_companies' => Organization::query()
                ->latest()
                ->limit(5)
                ->get(['id', 'name', 'created_at'])
                ->map(fn (Organization $organization): array => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'created_at' => $organization->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'recent_properties' => PropertyListing::query()
                ->latest()
                ->limit(5)
                ->get(['id', 'title', 'status', 'created_at'])
                ->map(fn (PropertyListing $property): array => [
                    'id' => $property->id,
                    'title' => $property->title,
                    'status' => $property->status,
                    'created_at' => $property->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
        ];

        return $this->success($payload, 'Dashboard summary retrieved successfully.');
    }
}
