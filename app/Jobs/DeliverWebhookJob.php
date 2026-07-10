<?php

namespace App\Jobs;

use App\Models\WebhookDeliveryLog;
use App\Services\Webhooks\WebhookDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $webhookDeliveryLogId)
    {
    }

    public function handle(): void
    {
        $log = WebhookDeliveryLog::query()->with('endpoint')->find($this->webhookDeliveryLogId);
        if (!$log) {
            return;
        }

        app(WebhookDeliveryService::class)->deliver($log, true);
    }
}
