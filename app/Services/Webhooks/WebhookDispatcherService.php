<?php

namespace App\Services\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Models\Organization;
use App\Models\User;
use App\Models\WebhookDeliveryLog;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WebhookDispatcherService
{
    public function dispatch(
        string $event,
        array $data,
        ?Organization $company = null,
        ?User $actor = null,
        ?string $deduplicationKey = null,
        array $context = []
    ): void {
        if (!$company) {
            return;
        }

        $endpoints = $this->resolveEndpoints($company->id, $event);

        foreach ($endpoints as $endpoint) {
            if ($deduplicationKey && $this->alreadyDispatched($endpoint->id, $event, $deduplicationKey)) {
                continue;
            }

            $eventId = (string) Str::uuid();
            $deliveryId = (string) Str::uuid();
            $payload = $this->buildPayload($event, $company->id, $data, $actor, $context, $eventId, $deliveryId);
            $log = WebhookDeliveryLog::query()->forceCreate([
                'id' => $deliveryId,
                'webhook_endpoint_id' => $endpoint->id,
                'company_id' => $company->id,
                'event_id' => $eventId,
                'event' => $event,
                'endpoint_url' => $endpoint->endpoint_url,
                'deduplication_key' => $deduplicationKey,
                'payload' => $payload,
                'delivery_status' => 'pending',
                'attempt_count' => 0,
                'retry_count' => (int) $endpoint->retry_count,
            ]);

            $this->logDispatchStart($endpoint, $event, $eventId, $deliveryId);

            if ($this->shouldThrottle($endpoint)) {
                $delaySeconds = $this->dispatchDelaySeconds($endpoint);
                $log->update([
                    'delivery_status' => 'retrying',
                    'next_retry_at' => now()->addSeconds($delaySeconds),
                    'error_message' => 'Webhook dispatch rate limited.',
                ]);

                Log::warning('Webhook dispatch rate limited.', [
                    'endpoint_id' => $endpoint->id,
                    'event' => $event,
                    'event_id' => $eventId,
                    'delivery_id' => $deliveryId,
                    'delay_seconds' => $delaySeconds,
                ]);

                DeliverWebhookJob::dispatch($log->id)
                    ->delay(now()->addSeconds($delaySeconds))
                    ->onQueue('webhooks');

                continue;
            }

            DeliverWebhookJob::dispatch($log->id)->onQueue('webhooks');
        }
    }

    public function activeEvents(): array
    {
        return config('webhooks.events', []);
    }

    public function registry(): array
    {
        return config('webhooks.registry', []);
    }

    /**
     * @return Collection<int, WebhookEndpoint>
     */
    private function resolveEndpoints(string $companyId, string $event): Collection
    {
        return WebhookEndpoint::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereJsonContains('events', $event)
            ->get();
    }

    private function alreadyDispatched(string $endpointId, string $event, string $deduplicationKey): bool
    {
        return WebhookDeliveryLog::query()
            ->where('webhook_endpoint_id', $endpointId)
            ->where('event', $event)
            ->where('deduplication_key', $deduplicationKey)
            ->whereIn('delivery_status', ['pending', 'retrying', 'delivered'])
            ->exists();
    }

    private function buildPayload(
        string $event,
        string $companyId,
        array $data,
        ?User $actor,
        array $context,
        string $eventId,
        string $deliveryId
    ): array {
        return array_filter([
            'version' => config('webhooks.version', '1.0'),
            'event' => $event,
            'event_id' => $eventId,
            'delivery_id' => $deliveryId,
            'timestamp' => Carbon::now()->toISOString(),
            'company_id' => $companyId,
            'actor_id' => $actor?->id,
            'data' => $data,
            'context' => $context !== [] ? $context : null,
        ], static fn ($value): bool => $value !== null);
    }

    private function shouldThrottle(WebhookEndpoint $endpoint): bool
    {
        $limit = max(1, (int) config('webhooks.dispatch_rate_limit_per_minute', 60));
        $decay = max(1, (int) config('webhooks.dispatch_rate_limit_decay_seconds', 60));
        $key = $this->rateLimitKey($endpoint->id);

        return !RateLimiter::attempt($key, $limit, static fn (): bool => true, $decay);
    }

    private function dispatchDelaySeconds(WebhookEndpoint $endpoint): int
    {
        $key = $this->rateLimitKey($endpoint->id);

        return max(1, RateLimiter::availableIn($key));
    }

    private function rateLimitKey(string $endpointId): string
    {
        return sprintf('webhook-dispatch:%s', $endpointId);
    }

    private function logDispatchStart(WebhookEndpoint $endpoint, string $event, string $eventId, string $deliveryId): void
    {
        Log::info('Webhook dispatch queued.', [
            'endpoint_id' => $endpoint->id,
            'endpoint_name' => $endpoint->name,
            'event' => $event,
            'event_id' => $eventId,
            'delivery_id' => $deliveryId,
        ]);
    }
}
