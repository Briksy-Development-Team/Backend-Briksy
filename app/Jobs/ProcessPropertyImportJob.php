<?php

namespace App\Jobs;

use App\Models\PropertyImport;
use App\Services\Imports\PropertyImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessPropertyImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 0;

    public function __construct(public readonly string $propertyImportId)
    {
    }

    public function handle(PropertyImportService $propertyImportService): void
    {
        $import = PropertyImport::query()->find($this->propertyImportId);

        if (!$import) {
            return;
        }

        $import->update([
            'status' => 'processing',
            'started_at' => $import->started_at ?? now(),
            'last_error' => null,
        ]);

        try {
            $result = $propertyImportService->process($import);
            $summary = $result['summary'] ?? [];
            $failedRows = $result['failed_rows'] ?? [];

            $errorReportPath = $failedRows !== []
                ? $propertyImportService->buildErrorReport($import, $failedRows)
                : null;

            $import->update([
                'status' => 'completed',
                'summary' => $summary,
                'total_rows' => $summary['total_rows'] ?? 0,
                'valid_rows' => $summary['valid_rows'] ?? 0,
                'invalid_rows' => $summary['failed_rows'] ?? 0,
                'duplicate_rows' => $summary['duplicate_rows'] ?? 0,
                'missing_required_rows' => $summary['missing_required_rows'] ?? 0,
                'imported_rows' => $summary['imported_rows'] ?? 0,
                'failed_rows' => $summary['failed_rows'] ?? 0,
                'skipped_rows' => $summary['skipped_rows'] ?? 0,
                'progress' => 100,
                'error_report_path' => $errorReportPath,
                'finished_at' => now(),
            ]);
        } catch (Throwable $throwable) {
            $import->update([
                'status' => 'failed',
                'last_error' => $throwable->getMessage(),
                'finished_at' => now(),
            ]);

            throw $throwable;
        }
    }
}
