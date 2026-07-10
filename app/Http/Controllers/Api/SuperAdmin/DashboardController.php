<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PropertyListing;
use App\Models\VisitorLog;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Properties\PropertyWorkflow;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $propertySummary = DB::table('property_listings')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) as draft")
            ->selectRaw("SUM(CASE WHEN status = 'Pending Review' THEN 1 ELSE 0 END) as pending_review")
            ->selectRaw("SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved")
            ->selectRaw("SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected")
            ->selectRaw("SUM(CASE WHEN status = 'Published' THEN 1 ELSE 0 END) as published")
            ->selectRaw("SUM(CASE WHEN status = 'Archived' THEN 1 ELSE 0 END) as archived")
            ->selectRaw("SUM(CASE WHEN status = 'Approved' AND location_verified = 0 THEN 1 ELSE 0 END) as awaiting_location_verification")
            ->selectRaw("SUM(CASE WHEN status = 'Published' AND DATE(published_at) = CURRENT_DATE THEN 1 ELSE 0 END) as published_today")
            ->first();

        $payload = [
            'total_companies' => Organization::query()->count(),
            'active_plans' => SubscriptionPlan::query()->where('is_active', true)->count(),
            'total_orders' => DB::table('subscriptions')->count(),
            'plan_requests' => 0,
            'active_subscriptions' => DB::table('subscriptions')->where('status', 'active')->count(),
            'revenue_this_month' => (float) DB::table('subscriptions')
                ->where('status', 'active')
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
            'property_summary' => [
                'total' => (int) ($propertySummary->total ?? 0),
                'draft' => (int) ($propertySummary->draft ?? 0),
                'pending_review' => (int) ($propertySummary->pending_review ?? 0),
                'approved' => (int) ($propertySummary->approved ?? 0),
                'rejected' => (int) ($propertySummary->rejected ?? 0),
                'published' => (int) ($propertySummary->published ?? 0),
                'archived' => (int) ($propertySummary->archived ?? 0),
                'awaiting_location_verification' => (int) ($propertySummary->awaiting_location_verification ?? 0),
                'published_today' => (int) ($propertySummary->published_today ?? 0),
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
            'trend_series' => collect(range(5, 0))->map(function (int $offset): array {
                $start = Carbon::now()->startOfMonth()->subMonths($offset);
                $end = $start->copy()->endOfMonth();
                $companies = Organization::query()->whereBetween('created_at', [$start, $end])->count();
                $subscriptions = DB::table('subscriptions')->whereBetween('created_at', [$start, $end])->count();
                $revenue = (float) DB::table('subscriptions')
                    ->where('status', 'active')
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('amount');

                return [
                    'label' => $start->format('M Y'),
                    'companies' => $companies,
                    'properties' => PropertyListing::query()->whereBetween('created_at', [$start, $end])->count(),
                    'subscriptions' => $subscriptions,
                'revenue' => $revenue,
                'company_conversion_rate' => $companies > 0 ? round(($subscriptions / $companies) * 100, 1) : 0.0,
            ];
            })->values()->all(),
            'lead_funnel' => $this->leadFunnel($request),
            'lead_source_funnel' => $this->leadSourceFunnel($request),
            'monthly_pipeline' => $this->monthlyPipeline($request),
            'agent_leaderboard' => $this->agentLeaderboard($request),
        ];

        return $this->success($payload, 'Dashboard summary retrieved successfully.');
    }

    private function leadSourceLabel(?string $source): string
    {
        $normalized = Str::of((string) $source)
            ->trim()
            ->lower()
            ->replace(['-', '_'], ' ')
            ->squish()
            ->toString();

        return match ($normalized) {
            'property listing', 'property', 'listing', 'property page' => 'Property Listings',
            'referral', 'referrals' => 'Referrals',
            'manual', 'staff', 'agent' => 'Manual / Staff',
            'website', 'web' => 'Website',
            default => Str::headline($normalized ?: 'Direct'),
        };
    }

    private function resolveDateRange(Request $request): array
    {
        $from = $request->filled('filter.date_from')
            ? Carbon::parse($request->input('filter.date_from'))->startOfDay()
            : now()->startOfMonth()->subMonths(5)->startOfDay();
        $to = $request->filled('filter.date_to')
            ? Carbon::parse($request->input('filter.date_to'))->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    private function leadSourceFunnel(Request $request): array
    {
        [$start, $end] = $this->resolveDateRange($request);
        $query = Inquiry::query()->whereBetween('created_at', [$start, $end]);
        if ($request->filled('filter.organization_id')) {
            $query->where('organization_id', $request->string('filter.organization_id')->toString());
        }

        $totalInquiries = $query->count();
        if ($totalInquiries === 0) {
            return [];
        }

        $sourceExpression = Schema::hasColumn('inquiries', 'lead_source')
            ? "COALESCE(NULLIF(lead_source, ''), CASE WHEN property_listing_id IS NOT NULL THEN 'property_listing' ELSE 'direct' END)"
            : "CASE WHEN property_listing_id IS NOT NULL THEN 'property_listing' ELSE 'direct' END";

        return $query
            ->selectRaw("{$sourceExpression} as source")
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw($sourceExpression)
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'label' => $this->leadSourceLabel($row->source),
                'value' => (int) $row->total,
                'share' => round(((int) $row->total / $totalInquiries) * 100, 1),
            ])
            ->values()
            ->all();
    }

    private function leadFunnel(Request $request): array
    {
        [$start, $end] = $this->resolveDateRange($request);
        $query = VisitorLog::query()->whereBetween('created_at', [$start, $end]);
        if ($request->filled('filter.organization_id')) {
            $query->where('organization_id', $request->string('filter.organization_id')->toString());
        }

        $visited = $query->count();

        $inquiryQuery = Inquiry::query()->whereBetween('created_at', [$start, $end]);
        $orderQuery = Order::query()->whereBetween('created_at', [$start, $end]);
        if ($request->filled('filter.organization_id')) {
            $organizationId = $request->string('filter.organization_id')->toString();
            $inquiryQuery->where('organization_id', $organizationId);
            $orderQuery->where('organization_id', $organizationId);
        }

        $inquiries = $inquiryQuery->count();
        $qualified = (clone $inquiryQuery)
            ->where(function ($leadQuery): void {
                $leadQuery->whereNotNull('property_listing_id')
                    ->orWhere(function ($subQuery): void {
                        if (Schema::hasColumn('inquiries', 'lead_source')) {
                            $subQuery->whereNotNull('lead_source')
                                ->where('lead_source', '!=', 'direct');
                        }
                    });
            })
            ->count();
        $won = $orderQuery->where('payment_status', 'paid')->count();

        return [
            ['stage' => 'Visited', 'value' => $visited],
            ['stage' => 'Inquiry', 'value' => $inquiries],
            ['stage' => 'Qualified', 'value' => $qualified],
            ['stage' => 'Won', 'value' => $won],
        ];
    }

    private function monthlyPipeline(Request $request): array
    {
        [$start, $end] = $this->resolveDateRange($request);
        return $this->monthBuckets($start, $end)->map(function (array $bucket) use ($request): array {
            $inquiryQuery = Inquiry::query()->whereBetween('created_at', [$bucket['start'], $bucket['end']]);
            $orderQuery = Order::query()->whereBetween('created_at', [$bucket['start'], $bucket['end']]);

            if ($request->filled('filter.organization_id')) {
                $organizationId = $request->string('filter.organization_id')->toString();
                $inquiryQuery->where('organization_id', $organizationId);
                $orderQuery->where('organization_id', $organizationId);
            }

            $inquiries = $inquiryQuery->count();
            $orders = $orderQuery->count();

            return [
                'label' => $bucket['label'],
                'inquiries' => $inquiries,
                'orders' => $orders,
                'revenue' => (float) (clone $orderQuery)
                    ->where('payment_status', 'paid')
                    ->sum('total_amount'),
                'close_rate' => $inquiries > 0 ? round(($orders / $inquiries) * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    private function agentLeaderboard(Request $request): array
    {
        [$start, $end] = $this->resolveDateRange($request);
        $query = User::query()
            ->with(['roles', 'organization'])
            ->whereHas('roles', static function ($roleQuery): void {
                $roleQuery->whereIn('roles.name', ['admin_staff', 'super_admin_employee']);
            });

        if ($request->filled('filter.role')) {
            $query->whereHas('roles', static function ($roleQuery) use ($request): void {
                $roleQuery->where('roles.name', $request->string('filter.role')->toString());
            });
        }

        if ($request->filled('filter.agent_id')) {
            $query->whereKey($request->string('filter.agent_id')->toString());
        }

        if ($request->filled('filter.organization_id')) {
            $query->where('organization_id', $request->string('filter.organization_id')->toString());
        }

        return $query->get()
            ->map(function (User $user) use ($start, $end): array {
                $organizationId = $user->organization_id;
                if (!$organizationId) {
                    return [
                        'id' => $user->id,
                        'name' => $user->display_name ?: $user->name,
                        'role' => $user->roles->pluck('name')->first() ?? 'admin_staff',
                        'organization' => null,
                        'properties' => 0,
                        'inquiries' => 0,
                        'orders' => 0,
                        'revenue' => 0.0,
                        'conversion_rate' => 0.0,
                    ];
                }

                $inquiries = Inquiry::query()
                    ->where('organization_id', $organizationId)
                    ->where('staff_id', $user->id)
                    ->whereBetween('created_at', [$start, $end])
                    ->count();

                $orders = Order::query()
                    ->where('organization_id', $organizationId)
                    ->where('user_id', $user->id)
                    ->whereBetween('created_at', [$start, $end])
                    ->count();

                $properties = PropertyListing::query()
                    ->where('org_id', $organizationId)
                    ->where('creator_id', $user->id)
                    ->whereBetween('created_at', [$start, $end])
                    ->count();

                $revenue = (float) Order::query()
                    ->where('organization_id', $organizationId)
                    ->where('user_id', $user->id)
                    ->where('payment_status', 'paid')
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('total_amount');

                return [
                    'id' => $user->id,
                    'name' => $user->display_name ?: $user->name,
                    'role' => $user->roles->pluck('name')->first() ?? 'admin_staff',
                    'organization' => $user->organization?->name,
                    'properties' => $properties,
                    'inquiries' => $inquiries,
                    'orders' => $orders,
                    'revenue' => $revenue,
                    'conversion_rate' => $inquiries > 0 ? round(($orders / $inquiries) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->take(5)
            ->all();
    }

    private function monthBuckets(Carbon $start, Carbon $end): \Illuminate\Support\Collection
    {
        $cursor = $start->copy()->startOfMonth();
        $limit = $end->copy()->startOfMonth();
        $buckets = collect();

        while ($cursor->lte($limit)) {
            $bucketStart = $cursor->copy()->startOfMonth();
            $bucketEnd = $cursor->copy()->endOfMonth();

            if ($bucketStart->lt($start)) {
                $bucketStart = $start->copy();
            }

            if ($bucketEnd->gt($end)) {
                $bucketEnd = $end->copy();
            }

            $buckets->push([
                'label' => $bucketStart->format('M Y'),
                'start' => $bucketStart,
                'end' => $bucketEnd,
            ]);

            $cursor->addMonthNoOverflow();
        }

        return $buckets;
    }
}
