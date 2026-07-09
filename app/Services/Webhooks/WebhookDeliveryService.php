<?php

namespace App\Services\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookDeliveryLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookDeliveryService
{
    /**
     * Deliver a webhook log immediately and optionally schedule retries.
     *
     * @return array{success:bool,http_status:?int,response_time_ms:?int,response_body:?string,signature:?string,delivery_status:string}
     */
    public function deliver(WebhookDeliveryLog $log, bool $queueRetries = true): array
    {
        $log->loadMissing('endpoint');
        $endpoint = $log->endpoint;

        if (!$endpoint) {
            return $this->markFailed($log, 'Webhook endpoint not found.');
        }

        if ($endpoint->status !== 'active') {
            return $this->markFailed($log, 'Webhook endpoint is disabled.');
        }

        if (!$this->isHttpsUrl($endpoint->endpoint_url)) {
            return $this->markFailed($log, 'Webhook endpoint must use HTTPS.');
        }

        $payload = $log->payload ?? [];
        $eventId = (string) ($payload['event_id'] ?? $log->event_id ?? $log->id);
        $deliveryId = (string) ($payload['delivery_id'] ?? $log->id);
        $timestamp = (string) ($payload['timestamp'] ?? now()->toISOString());
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, (string) $endpoint->secret_key);
        $startedAt = microtime(true);
        $attempt = (int) $log->attempt_count + 1;

        Log::info('Webhook delivery started.', [
            'webhook_delivery_log_id' => $log->id,
            'webhook_endpoint_id' => $endpoint->id,
            'event' => $log->event,
            'event_id' => $eventId,
            'delivery_id' => $deliveryId,
            'attempt' => $attempt,
        ]);

        $log->forceFill([
            'attempt_count' => $attempt,
            'delivery_status' => $attempt > 1 ? 'retrying' : 'pending',
            'last_attempt_at' => now(),
            'signature' => $signature,
        ])->save();

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('webhooks.timeout', 10))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Brisky-Signature' => $signature,
                    'X-Brisky-Event' => (string) ($payload['event'] ?? $log->event),
                    'X-Brisky-Timestamp' => $timestamp,
                    'X-Brisky-Event-Id' => $eventId,
                    'X-Brisky-Delivery-Id' => $deliveryId,
                    'X-Brisky-Version' => (string) data_get($payload, 'version', config('webhooks.version', '1.0')),
                ])
                ->post($endpoint->endpoint_url, $payload);

            $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($response->successful()) {
                $log->update([
                    'delivery_status' => 'delivered',
                    'http_status' => $response->status(),
                    'response_body' => $response->body(),
                    'response_time_ms' => $responseTimeMs,
                    'delivered_at' => now(),
                    'error_message' => null,
                    'next_retry_at' => null,
                    'failed_at' => null,
                ]);

                Log::info('Webhook delivery successful.', [
                    'webhook_delivery_log_id' => $log->id,
                    'webhook_endpoint_id' => $endpoint->id,
                    'event' => $log->event,
                    'event_id' => $eventId,
                    'delivery_id' => $deliveryId,
                    'http_status' => $response->status(),
                    'response_time_ms' => $responseTimeMs,
                ]);

                return [
                    'success' => true,
                    'http_status' => $response->status(),
                    'response_time_ms' => $responseTimeMs,
                    'response_body' => $response->body(),
                    'signature' => $signature,
                    'delivery_status' => 'delivered',
                ];
            }

            return $this->handleFailure($log, $response->status(), $response->body(), $responseTimeMs, $queueRetries);
        } catch (Throwable $throwable) {
            $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

            return $this->handleFailure($log, null, $throwable->getMessage(), $responseTimeMs, $queueRetries);
        }
    }

    private function handleFailure(WebhookDeliveryLog $log, ?int $statusCode, ?string $responseBody, int $responseTimeMs, bool $queueRetries): array
    {
        $retryLimit = max(0, (int) $log->retry_count);
        $attempt = (int) $log->attempt_count;
        $hasMoreRetries = $attempt <= $retryLimit;
        $delaySeconds = (int) min(3600, pow(2, max(0, $attempt - 1)) * 60);
        $deliveryStatus = $hasMoreRetries ? 'retrying' : 'failed';

        $log->update([
            'http_status' => $statusCode,
            'response_body' => $responseBody,
            'response_time_ms' => $responseTimeMs,
            'error_message' => $statusCode && $statusCode >= 400
                ? sprintf('Webhook request failed with HTTP %d.', $statusCode)
                : $responseBody,
            'delivery_status' => $deliveryStatus,
            'next_retry_at' => $hasMoreRetries ? now()->addSeconds($delaySeconds) : null,
            'failed_at' => $hasMoreRetries ? null : now(),
            'dead_lettered_at' => $hasMoreRetries ? null : now(),
        ]);

        if ($hasMoreRetries) {
            Log::warning('Webhook delivery retry scheduled.', [
                'webhook_delivery_log_id' => $log->id,
                'event' => $log->event,
                'attempt' => $attempt,
                'retry_limit' => $retryLimit,
                'delay_seconds' => $delaySeconds,
            ]);
        } else {
            Log::error('Webhook delivery moved to dead letter queue.', [
                'webhook_delivery_log_id' => $log->id,
                'event' => $log->event,
                'attempt' => $attempt,
                'retry_limit' => $retryLimit,
                'http_status' => $statusCode,
            ]);
        }

        if ($queueRetries && $hasMoreRetries) {
            DeliverWebhookJob::dispatch($log->id)
                ->delay(now()->addSeconds($delaySeconds))
                ->onQueue('webhooks');
        }

        return [
            'success' => false,
            'http_status' => $statusCode,
            'response_time_ms' => $responseTimeMs,
            'response_body' => $responseBody,
            'signature' => $log->signature,
            'delivery_status' => $deliveryStatus,
        ];
    }

    private function markFailed(WebhookDeliveryLog $log, string $message): array
    {
        $log->update([
            'delivery_status' => 'failed',
            'error_message' => $message,
            'failed_at' => now(),
            'dead_lettered_at' => now(),
        ]);

        Log::error('Webhook delivery permanently failed.', [
            'webhook_delivery_log_id' => $log->id,
            'event' => $log->event,
            'message' => $message,
        ]);

        return [
            'success' => false,
            'http_status' => null,
            'response_time_ms' => null,
            'response_body' => $message,
            'signature' => $log->signature,
            'delivery_status' => 'failed',
        ];
    }

    private function isHttpsUrl(string $url): bool
    {
        return str_starts_with(strtolower($url), 'https://');
    }
}
