<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Models\Inquiry;
use App\Models\Organization;
use App\Models\Order;
use App\Models\PropertyListing;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $currentSubscription = $organization->currentSubscription?->loadMissing(['plan', 'addons.addon']);

        $metrics = [
            'team_members' => User::query()->where('organization_id', $organization->id)->count(),
            'properties' => PropertyListing::query()->where('org_id', $organization->id)->count(),
            'published_properties' => PropertyListing::query()->where('org_id', $organization->id)->where('status', 'Published')->count(),
            'services' => Service::query()->where('organization_id', $organization->id)->count(),
            'inquiries' => Inquiry::query()->where('organization_id', $organization->id)->count(),
            'new_inquiries' => Inquiry::query()->where('organization_id', $organization->id)->where('status', 'new')->count(),
            'orders' => Order::query()->where('organization_id', $organization->id)->count(),
            'active_orders' => Order::query()->where('organization_id', $organization->id)->where('order_status', 'active')->count(),
            'revenue' => (float) Order::query()->where('organization_id', $organization->id)->where('payment_status', 'paid')->sum('total_amount'),
            'revenue_this_month' => (float) Order::query()
                ->where('organization_id', $organization->id)
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('total_amount'),
            'average_order_value' => (float) (
                Order::query()
                    ->where('organization_id', $organization->id)
                    ->where('payment_status', 'paid')
                    ->avg('total_amount') ?? 0
            ),
            'referrals' => Organization::query()->where('referred_by_organization_id', $organization->id)->count(),
        ];

        $overallLeadConversionRate = $metrics['inquiries'] > 0
            ? round(($metrics['orders'] / $metrics['inquiries']) * 100, 1)
            : 0.0;

        [$rangeStart, $rangeEnd] = $this->resolveDateRange($request);
        $agentFilters = $this->agentFilters($request);

        $leadSourceFunnel = $this->leadSourceFunnel($organization->id, $rangeStart, $rangeEnd);
        $monthlyPipeline = $this->monthlyPipeline($organization->id, $rangeStart, $rangeEnd);
        $agentLeaderboard = $this->agentLeaderboard($organization->id, $rangeStart, $rangeEnd, $agentFilters);
        $leadFunnel = $this->leadFunnel($organization->id, $rangeStart, $rangeEnd);

        $months = collect(range(5, 0))->map(function (int $offset) {
            $start = Carbon::now()->startOfMonth()->subMonths($offset);
            $end = $start->copy()->endOfMonth();

            return [
                'key' => $start->format('Y-m'),
                'label' => $start->format('M Y'),
                'start' => $start,
                'end' => $end,
            ];
        });

        $trendSeries = $months->map(function (array $month) use ($organization): array {
            $start = $month['start'];
            $end = $month['end'];

            return [
                'label' => $month['label'],
                'properties' => PropertyListing::query()
                    ->where('org_id', $organization->id)
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
                'inquiries' => Inquiry::query()
                    ->where('organization_id', $organization->id)
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
                'orders' => Order::query()
                    ->where('organization_id', $organization->id)
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
                'revenue' => (float) Order::query()
                    ->where('organization_id', $organization->id)
                    ->where('payment_status', 'paid')
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('total_amount'),
                'lead_conversion_rate' => (float) (
                    ($inquiries = Inquiry::query()
                        ->where('organization_id', $organization->id)
                        ->whereBetween('created_at', [$start, $end])
                        ->count()) > 0
                        ? round(
                            (
                                Order::query()
                                    ->where('organization_id', $organization->id)
                                    ->whereBetween('created_at', [$start, $end])
                                    ->count() / $inquiries
                            ) * 100,
                            1
                        )
                        : 0.0
                ),
            ];
        })->values()->all();

        $recentProperties = PropertyListing::query()
            ->where('org_id', $organization->id)
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
            ->all();

        $recentInquiries = Inquiry::query()
            ->where('organization_id', $organization->id)
            ->latest()
            ->limit(5)
            ->get(['id', 'subject', 'status', 'created_at'])
            ->map(fn (Inquiry $inquiry): array => [
                'id' => $inquiry->id,
                'subject' => $inquiry->subject,
                'status' => $inquiry->status,
                'created_at' => $inquiry->created_at?->toISOString(),
            ])
            ->values()
            ->all();

        $recentOrders = Order::query()
            ->where('organization_id', $organization->id)
            ->latest()
            ->limit(5)
            ->get(['id', 'reference_no', 'order_number', 'order_status', 'payment_status', 'total_amount', 'created_at'])
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'reference_no' => $order->display_number,
                'order_status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'total_amount' => $order->total_amount !== null ? (float) $order->total_amount : null,
                'created_at' => $order->created_at?->toISOString(),
            ])
            ->values()
            ->all();

        return $this->success([
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'trading_name' => $organization->trading_name,
                'referral_code' => $organization->referral_code,
                'status' => $organization->subscriptionStatus(),
            ],
            'current_subscription' => $currentSubscription ? [
                'id' => $currentSubscription->id,
                'plan_name' => $currentSubscription->plan?->name,
                'billing_cycle' => $currentSubscription->billing_cycle,
                'status' => $currentSubscription->status,
                'amount' => $currentSubscription->amount !== null ? (float) $currentSubscription->amount : null,
                'currency' => $currentSubscription->currency,
                'current_period_end' => $currentSubscription->current_period_end?->toISOString(),
            ] : null,
            'metrics' => $metrics,
            'lead_conversion_rate' => $overallLeadConversionRate,
            'trend_series' => $trendSeries,
            'lead_funnel' => $leadFunnel,
            'lead_source_funnel' => $leadSourceFunnel,
            'monthly_pipeline' => $monthlyPipeline,
            'agent_leaderboard' => $agentLeaderboard,
            'recent_properties' => $recentProperties,
            'recent_inquiries' => $recentInquiries,
            'recent_orders' => $recentOrders,
        ], 'Dashboard analytics retrieved successfully.');
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->user()?->organization;
        abort_unless($organization, 403, 'Admin account is not assigned to an organization.');

        return $organization;
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

    private function agentFilters(Request $request): array
    {
        return [
            'role' => $request->string('filter.role')->toString() ?: null,
            'agent_id' => $request->string('filter.agent_id')->toString() ?: null,
        ];
    }

    private function leadSourceFunnel(string $organizationId, Carbon $start, Carbon $end): array
    {
        $totalInquiries = Inquiry::query()
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$start, $end])
            ->count();
        if ($totalInquiries === 0) {
            return [];
        }

        $sourceExpression = Schema::hasColumn('inquiries', 'lead_source')
            ? "COALESCE(NULLIF(lead_source, ''), CASE WHEN property_listing_id IS NOT NULL THEN 'property_listing' ELSE 'direct' END)"
            : "CASE WHEN property_listing_id IS NOT NULL THEN 'property_listing' ELSE 'direct' END";

        return Inquiry::query()
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$start, $end])
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

    private function leadFunnel(string $organizationId, Carbon $start, Carbon $end): array
    {
        $visited = $this->visitCount($organizationId, $start, $end);
        $inquiries = Inquiry::query()
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $qualified = Inquiry::query()
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$start, $end])
            ->where(function ($query): void {
                $query->whereNotNull('property_listing_id')
                    ->orWhere(function ($subQuery): void {
                        if (Schema::hasColumn('inquiries', 'lead_source')) {
                            $subQuery->whereNotNull('lead_source')
                                ->where('lead_source', '!=', 'direct');
                        }
                    });
            })
            ->count();
        $won = Order::query()
            ->where('organization_id', $organizationId)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        return [
            ['stage' => 'Visited', 'value' => $visited],
            ['stage' => 'Inquiry', 'value' => $inquiries],
            ['stage' => 'Qualified', 'value' => $qualified],
            ['stage' => 'Won', 'value' => $won],
        ];
    }

    private function monthlyPipeline(string $organizationId, Carbon $start, Carbon $end): array
    {
        return $this->monthBuckets($start, $end)->map(function (array $bucket) use ($organizationId): array {
            $bucketStart = $bucket['start'];
            $bucketEnd = $bucket['end'];

            $inquiries = Inquiry::query()
                ->where('organization_id', $organizationId)
                ->whereBetween('created_at', [$bucketStart, $bucketEnd])
                ->count();

            $orders = Order::query()
                ->where('organization_id', $organizationId)
                ->whereBetween('created_at', [$bucketStart, $bucketEnd])
                ->count();

            return [
                'label' => $bucket['label'],
                'inquiries' => $inquiries,
                'orders' => $orders,
                'revenue' => (float) Order::query()
                    ->where('organization_id', $organizationId)
                    ->where('payment_status', 'paid')
                    ->whereBetween('created_at', [$bucketStart, $bucketEnd])
                    ->sum('total_amount'),
                'close_rate' => $inquiries > 0 ? round(($orders / $inquiries) * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    private function agentLeaderboard(string $organizationId, Carbon $start, Carbon $end, array $filters): array
    {
        $query = User::query()
            ->with('roles')
            ->where('organization_id', $organizationId)
            ->whereHas('roles', static function ($roleQuery): void {
                $roleQuery->whereIn('roles.name', ['admin', 'admin_staff']);
            });

        if (!empty($filters['role'])) {
            $query->whereHas('roles', static function ($roleQuery) use ($filters): void {
                $roleQuery->where('roles.name', $filters['role']);
            });
        }

        if (!empty($filters['agent_id'])) {
            $query->whereKey($filters['agent_id']);
        }

        return $query->get()
            ->map(function (User $user) use ($organizationId, $start, $end): array {
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

    private function visitCount(string $organizationId, Carbon $start, Carbon $end): int
    {
        if (!Schema::hasTable('visitor_logs')) {
            return 0;
        }

        return DB::table('visitor_logs')
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }
}
