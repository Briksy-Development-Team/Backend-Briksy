<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Admin\WebhookDeliveryLogIndexRequest;
use App\Http\Requests\Api\Admin\WebhookEndpointIndexRequest;
use App\Http\Requests\Api\Admin\WebhookEndpointStoreRequest;
use App\Http\Requests\Api\Admin\WebhookEndpointUpdateRequest;
use App\Http\Resources\Admin\WebhookDeliveryLogResource;
use App\Http\Resources\Admin\WebhookEndpointResource;
use App\Jobs\DeliverWebhookJob;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use App\Models\WebhookDeliveryLog;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookDeliveryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function __construct(private readonly WebhookDeliveryService $webhookDeliveryService)
    {
    }

    public function index(WebhookEndpointIndexRequest $request): JsonResponse
    {
        $organization = $this->organization($request);
        $query = $this->endpointQuery($request, $organization?->id);
        $stats = $this->buildStats($request, $organization?->id);
        $endpoints = $query->get();

        return $this->success([
            'endpoints' => WebhookEndpointResource::collection($endpoints)->resolve(),
            'supported_events' => config('webhooks.events', []),
            'event_registry' => config('webhooks.registry', []),
            'event_categories' => config('webhooks.categories', []),
            'stats' => $stats,
        ], 'Webhook endpoints retrieved successfully.');
    }

    public function store(WebhookEndpointStoreRequest $request): JsonResponse
    {
        $organization = $this->organization($request);
        $user = $request->user();
        $validated = $request->validated();

        $endpoint = WebhookEndpoint::query()->create([
            'company_id' => $organization->id,
            'name' => $validated['name'],
            'endpoint_url' => $validated['endpoint_url'],
            'secret_key' => $validated['secret_key'] ?? $this->generateSecret(),
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'events' => array_values($validated['events']),
            'retry_count' => (int) ($validated['retry_count'] ?? config('webhooks.max_retry_count', 5)),
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        $this->recordActivity($request, 'webhook_endpoint_created', 'webhook', sprintf('Webhook endpoint "%s" created.', $endpoint->name), $endpoint->id, null, $endpoint->toArray());

        return $this->created(
            new WebhookEndpointResource($endpoint->fresh()->load(['creator', 'updater', 'latestDelivery'])),
            'Webhook endpoint created successfully.'
        );
    }

    public function show(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $this->authorizeEndpoint($request, $webhookEndpoint);

        return $this->success(
            new WebhookEndpointResource($webhookEndpoint->loadMissing(['creator', 'updater', 'latestDelivery'])),
            'Webhook endpoint retrieved successfully.'
        );
    }

    public function update(WebhookEndpointUpdateRequest $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $this->authorizeEndpoint($request, $webhookEndpoint);
        $validated = $request->validated();
        $original = $webhookEndpoint->replicate()->toArray();
        $originalStatus = $webhookEndpoint->status;

        $webhookEndpoint->fill([
            'name' => $validated['name'],
            'endpoint_url' => $validated['endpoint_url'],
            'secret_key' => $validated['secret_key'] ?? $webhookEndpoint->secret_key,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'events' => array_values($validated['events']),
            'retry_count' => (int) ($validated['retry_count'] ?? $webhookEndpoint->retry_count),
            'updated_by' => $request->user()?->id,
        ]);
        $webhookEndpoint->save();
        if ($originalStatus !== $validated['status']) {
            $this->recordActivity(
                $request,
                $validated['status'] === 'active' ? 'webhook_endpoint_enabled' : 'webhook_endpoint_disabled',
                'webhook',
                sprintf('Webhook endpoint "%s" status changed to %s.', $webhookEndpoint->name, $validated['status']),
                $webhookEndpoint->id,
                $original,
                $webhookEndpoint->toArray()
            );
        }

        $this->recordActivity($request, 'webhook_endpoint_updated', 'webhook', sprintf('Webhook endpoint "%s" updated.', $webhookEndpoint->name), $webhookEndpoint->id, $original, $webhookEndpoint->toArray());

        return $this->success(
            new WebhookEndpointResource($webhookEndpoint->fresh()->load(['creator', 'updater', 'latestDelivery'])),
            'Webhook endpoint updated successfully.'
        );
    }

    public function destroy(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $this->authorizeEndpoint($request, $webhookEndpoint);
        $snapshot = $webhookEndpoint->toArray();
        $name = $webhookEndpoint->name;

        $webhookEndpoint->delete();

        $this->recordActivity($request, 'webhook_endpoint_deleted', 'webhook', sprintf('Webhook endpoint "%s" deleted.', $name), $webhookEndpoint->id, $snapshot, null);

        return $this->success([], 'Webhook endpoint deleted successfully.');
    }

    public function logs(WebhookDeliveryLogIndexRequest $request): JsonResponse
    {
        $organization = $this->organization($request);
        $query = $this->logQuery($request, $organization?->id);
        $logs = $query->paginate($request->perPage());

        return $this->paginated(
            WebhookDeliveryLogResource::collection($logs)->resolve(),
            $logs,
            'Webhook delivery logs retrieved successfully.'
        );
    }

    public function stats(Request $request): JsonResponse
    {
        $organization = $this->organization($request);

        return $this->success($this->buildStats($request, $organization?->id), 'Webhook statistics retrieved successfully.');
    }

    public function showLog(Request $request, WebhookDeliveryLog $webhookDeliveryLog): JsonResponse
    {
        $this->authorizeLog($request, $webhookDeliveryLog);

        return $this->success(
            new WebhookDeliveryLogResource($webhookDeliveryLog->loadMissing('endpoint')),
            'Webhook delivery log retrieved successfully.'
        );
    }

    public function retry(Request $request, WebhookDeliveryLog $webhookDeliveryLog): JsonResponse
    {
        $this->authorizeLog($request, $webhookDeliveryLog);
        $webhookDeliveryLog->update([
            'delivery_status' => 'pending',
            'error_message' => null,
            'next_retry_at' => now(),
            'failed_at' => null,
            'dead_lettered_at' => null,
        ]);

        DeliverWebhookJob::dispatch($webhookDeliveryLog->id)->onQueue('webhooks');
        Log::info('Webhook delivery retry requested.', [
            'webhook_delivery_log_id' => $webhookDeliveryLog->id,
            'event' => $webhookDeliveryLog->event,
            'user_id' => $request->user()?->id,
        ]);

        $this->recordActivity($request, 'webhook_delivery_retry', 'webhook', sprintf('Webhook delivery "%s" retried manually.', $webhookDeliveryLog->event), $webhookDeliveryLog->id, null, $webhookDeliveryLog->fresh()->toArray());

        return $this->success(
            new WebhookDeliveryLogResource($webhookDeliveryLog->fresh()->loadMissing('endpoint')),
            'Webhook delivery retried successfully.'
        );
    }

    public function regenerateSecret(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $this->authorizeEndpoint($request, $webhookEndpoint);
        $oldValues = $webhookEndpoint->toArray();
        $webhookEndpoint->update([
            'secret_key' => $this->generateSecret(),
            'updated_by' => $request->user()?->id,
        ]);
        Log::info('Webhook secret regenerated.', [
            'webhook_endpoint_id' => $webhookEndpoint->id,
            'webhook_name' => $webhookEndpoint->name,
            'user_id' => $request->user()?->id,
        ]);

        $this->recordActivity($request, 'webhook_secret_regenerated', 'webhook', sprintf('Webhook secret regenerated for "%s".', $webhookEndpoint->name), $webhookEndpoint->id, $oldValues, $webhookEndpoint->toArray());

        return $this->success([
            'secret_key' => $webhookEndpoint->secret_key,
            'endpoint' => new WebhookEndpointResource($webhookEndpoint->fresh()->load(['creator', 'updater', 'latestDelivery'])),
        ], 'Webhook secret regenerated successfully.');
    }

    public function test(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $this->authorizeEndpoint($request, $webhookEndpoint);
        $eventId = (string) Str::uuid();
        $deliveryId = (string) Str::uuid();

        $log = WebhookDeliveryLog::query()->forceCreate([
            'id' => $deliveryId,
            'webhook_endpoint_id' => $webhookEndpoint->id,
            'company_id' => $webhookEndpoint->company_id,
            'event_id' => $eventId,
            'event' => 'webhook.test',
            'endpoint_url' => $webhookEndpoint->endpoint_url,
            'payload' => $this->buildTestPayload($request, $webhookEndpoint, $eventId, $deliveryId),
            'delivery_status' => 'pending',
            'attempt_count' => 0,
            'retry_count' => 0,
        ]);

        $result = $this->webhookDeliveryService->deliver($log, false);
        $log->refresh();
        Log::info('Webhook test execution completed.', [
            'webhook_endpoint_id' => $webhookEndpoint->id,
            'webhook_name' => $webhookEndpoint->name,
            'success' => $result['success'],
            'http_status' => $result['http_status'],
            'user_id' => $request->user()?->id,
        ]);

        $this->recordActivity($request, 'webhook_test_sent', 'webhook', sprintf('Webhook test sent for "%s".', $webhookEndpoint->name), $webhookEndpoint->id, null, $log->toArray());

        return $this->success([
            'log' => new WebhookDeliveryLogResource($log->loadMissing('endpoint')),
            'result' => $result,
        ], $result['success'] ? 'Webhook test sent successfully.' : 'Webhook test failed.');
    }

    public function exportLogs(WebhookDeliveryLogIndexRequest $request): JsonResponse
    {
        $organization = $this->organization($request);
        $query = $this->logQuery($request, $organization?->id);
        $logs = $query->get();

        return $this->success([
            'logs' => WebhookDeliveryLogResource::collection($logs)->resolve(),
        ], 'Filtered webhook logs exported for client-side download.');
    }

    private function organization(Request $request): ?Organization
    {
        $user = $request->user();
        if ($user?->hasRole('super_admin') || $user?->hasRole('super_admin_employee')) {
            return null;
        }

        $organization = $user?->organization;
        abort_unless($organization, 403, 'Admin account is not assigned to an organization.');

        return $organization;
    }

    private function authorizeEndpoint(Request $request, WebhookEndpoint $webhookEndpoint): void
    {
        $organization = $this->organization($request);
        if ($organization) {
            abort_unless($webhookEndpoint->company_id === $organization->id, 403);
        }
    }

    private function authorizeLog(Request $request, WebhookDeliveryLog $webhookDeliveryLog): void
    {
        $organization = $this->organization($request);
        if ($organization) {
            abort_unless($webhookDeliveryLog->company_id === $organization->id, 403);
        }
    }

    private function generateSecret(): string
    {
        return Str::random(48);
    }

    private function recordActivity(
        Request $request,
        string $action,
        string $module,
        string $description,
        ?string $subjectId,
        ?array $oldValues,
        ?array $newValues
    ): void {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        if (!$organizationId) {
            return;
        }

        ActivityLog::query()->create([
            'causer_id' => $user?->id,
            'subject_id' => $subjectId,
            'organization_id' => $organizationId,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'user_role' => $user?->roles->pluck('name')->first(),
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'method' => $request->method(),
            'route' => $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => [
                'source' => 'webhook',
            ],
        ]);
    }

    private function endpointQuery(WebhookEndpointIndexRequest $request, ?string $companyId): Builder
    {
        $query = WebhookEndpoint::query()
            ->with(['creator', 'updater', 'latestDelivery']);

        if ($companyId) {
            $query->where('company_id', $companyId);
        } elseif ($request->filled('filter.company_id')) {
            $query->where('company_id', $request->string('filter.company_id')->toString());
        }

        if ($search = $request->search()) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('endpoint_url', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        if ($event = $request->input('filter.event')) {
            $query->whereJsonContains('events', $event);
        }

        $sort = $request->sort() ?? 'created_at';
        $query->orderBy($sort, $request->direction());

        return $query;
    }

    private function logQuery(WebhookDeliveryLogIndexRequest $request, ?string $companyId): Builder
    {
        $query = WebhookDeliveryLog::query()->with('endpoint');

        if ($companyId) {
            $query->where('company_id', $companyId);
        } elseif ($request->filled('filter.company_id')) {
            $query->where('company_id', $request->string('filter.company_id')->toString());
        }

        if ($search = $request->search()) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('event', 'like', '%' . $search . '%')
                    ->orWhere('endpoint_url', 'like', '%' . $search . '%')
                    ->orWhere('delivery_status', 'like', '%' . $search . '%')
                    ->orWhere('error_message', 'like', '%' . $search . '%');
            });
        }

        if ($event = $request->input('filter.event')) {
            $query->where('event', $event);
        }

        if ($status = $request->input('filter.status')) {
            $query->where('delivery_status', $status);
        }

        if ($endpointId = $request->input('filter.endpoint_id')) {
            $query->where('webhook_endpoint_id', $endpointId);
        }

        if ($dateFrom = $request->input('filter.date_from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($dateFrom));
        }

        if ($dateTo = $request->input('filter.date_to')) {
            $query->whereDate('created_at', '<=', Carbon::parse($dateTo));
        }

        $sort = $request->sort() ?? 'created_at';
        $query->orderBy($sort, $request->direction());

        return $query;
    }

    private function buildStats(Request $request, ?string $companyId): array
    {
        $endpointQuery = WebhookEndpoint::query();
        $logQuery = WebhookDeliveryLog::query();

        if ($companyId) {
            $endpointQuery->where('company_id', $companyId);
            $logQuery->where('company_id', $companyId);
        } elseif ($request->filled('filter.company_id')) {
            $companyFilter = $request->string('filter.company_id')->toString();
            $endpointQuery->where('company_id', $companyFilter);
            $logQuery->where('company_id', $companyFilter);
        }

        $totalEndpoints = (clone $endpointQuery)->count();
        $activeEndpoints = (clone $endpointQuery)->where('status', 'active')->count();
        $totalDeliveries = (clone $logQuery)->count();
        $successfulDeliveries = (clone $logQuery)->where('delivery_status', 'delivered')->count();
        $failedDeliveries = (clone $logQuery)->whereIn('delivery_status', ['failed', 'dead_letter'])->count();
        $deadLetterDeliveries = (clone $logQuery)->where('delivery_status', 'dead_letter')->count();
        $retryQueue = (clone $logQuery)->where('delivery_status', 'retrying')->count();
        $successRate = $totalDeliveries > 0 ? round(($successfulDeliveries / $totalDeliveries) * 100, 1) : 0.0;
        $pendingRetries = (clone $logQuery)->where('delivery_status', 'retrying')->whereNotNull('next_retry_at')->count();
        $recentFailures = (clone $logQuery)
            ->whereIn('delivery_status', ['failed', 'dead_letter'])
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $problemEndpoints = (clone $endpointQuery)
            ->withCount(['deliveryLogs as failure_count' => fn (Builder $builder) => $builder->whereIn('delivery_status', ['failed', 'dead_letter'])])
            ->orderByDesc('failure_count')
            ->limit(5)
            ->get(['id', 'name', 'status'])
            ->map(fn (WebhookEndpoint $endpoint): array => [
                'id' => $endpoint->id,
                'name' => $endpoint->name,
                'status' => $endpoint->status,
                'failure_count' => (int) ($endpoint->failure_count ?? 0),
            ])
            ->values()
            ->all();

        return [
            'total_endpoints' => $totalEndpoints,
            'active_endpoints' => $activeEndpoints,
            'total_deliveries' => $totalDeliveries,
            'successful_deliveries' => $successfulDeliveries,
            'failed_deliveries' => $failedDeliveries,
            'dead_letter_deliveries' => $deadLetterDeliveries,
            'retry_queue' => $retryQueue,
            'success_rate' => $successRate,
            'pending_retries' => $pendingRetries,
            'recent_failures' => $recentFailures,
            'problem_endpoints' => $problemEndpoints,
        ];
    }

    private function buildTestPayload(Request $request, WebhookEndpoint $webhookEndpoint, string $eventId, string $deliveryId): array
    {
        return [
            'version' => config('webhooks.version', '1.0'),
            'event' => 'webhook.test',
            'event_id' => $eventId,
            'delivery_id' => $deliveryId,
            'timestamp' => now()->toISOString(),
            'company_id' => $webhookEndpoint->company_id,
            'data' => [
                'message' => 'This is a sample test event from Briksy.',
                'webhook_name' => $webhookEndpoint->name,
                'generated_by' => $request->user()?->name,
            ],
            'message_id' => (string) Str::uuid(),
        ];
    }
}
