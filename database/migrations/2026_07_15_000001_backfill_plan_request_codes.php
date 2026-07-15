<?php

use App\Models\PlanRequest;
use App\Services\DynamicIdGeneratorService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_requests') || ! Schema::hasColumn('plan_requests', 'request_code')) {
            return;
        }

        $generator = app(DynamicIdGeneratorService::class);

        PlanRequest::query()
            ->whereNull('request_code')
            ->orderBy('created_at')
            ->get()
            ->each(function (PlanRequest $planRequest) use ($generator): void {
                $planRequest->forceFill([
                    'request_code' => $generator->generate('plan_requests'),
                ])->save();
            });
    }

    public function down(): void
    {
        // Keep generated request codes once assigned.
    }
};
