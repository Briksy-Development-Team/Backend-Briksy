<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\ServiceImportAnalyzeRequest;
use App\Http\Requests\Api\SuperAdmin\ServiceImportPreviewRequest;
use App\Jobs\ProcessServiceImportJob;
use App\Models\BulkImport;
use App\Services\Imports\ImportTemplateService;
use App\Services\Imports\ServiceImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServiceImportController extends Controller
{
    public function __construct(
        private readonly ServiceImportService $serviceImportService,
        private readonly ImportTemplateService $templateService,
    ) {
    }

    public function meta(): JsonResponse
    {
        return $this->success(
            $this->serviceImportService->meta(),
            'Service import metadata retrieved successfully.'
        );
    }

    public function template(Request $request)
    {
        $format = strtolower($request->string('format', 'xlsx')->toString());
        $importFileName = $format === 'csv' ? 'service-import-template.csv' : 'service-import-template.xlsx';
        $path = $this->templateService->build(
            $this->serviceImportService->fields(),
            $this->serviceImportService->templateSampleRow(),
            $format === 'csv' ? 'csv' : 'xlsx',
            'Services',
            'service-import-template'
        );

        return response()->download($path, $importFileName)->deleteFileAfterSend(true);
    }

    public function analyze(ServiceImportAnalyzeRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $extension = strtolower($file?->getClientOriginalExtension() ?: $file?->extension() ?: 'csv');
        $directory = 'imports/service/'.Str::uuid();
        $storedPath = $file?->storeAs($directory, 'source.'.$extension, 'local');

        if (!$file || !$storedPath) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to store import file.',
            ], 422);
        }

        $import = BulkImport::query()->create([
            'org_id' => $request->user()?->organization_id,
            'created_by' => $request->user()?->id,
            'module' => 'service',
            'status' => 'analyzed',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'stored_disk' => 'local',
            'file_type' => $extension === 'csv' || $extension === 'txt' ? 'csv' : 'xlsx',
        ]);

        $analysis = $this->serviceImportService->analyze($import);

        $import->update([
            'source_columns' => $analysis['source_columns'],
            'preview' => $analysis,
        ]);

        return $this->success([
            'import' => $this->serializeImport($import->fresh()),
            ...$analysis,
            ...$this->serviceImportService->meta(),
        ], 'Service import file analyzed successfully.');
    }

    public function preview(ServiceImportPreviewRequest $request, BulkImport $bulkImport): JsonResponse
    {
        $this->assertImportOwnership($request, $bulkImport);

        abort_unless($bulkImport->module === 'service', 404);

        $mapping = $request->input('mapping', []);
        $preview = $this->serviceImportService->preview($bulkImport, $mapping);

        $bulkImport->update([
            'mapping' => $mapping,
            'preview' => $preview,
            'summary' => $preview['summary'] ?? null,
            'total_rows' => $preview['summary']['total_rows'] ?? 0,
            'valid_rows' => $preview['summary']['valid_rows'] ?? 0,
            'invalid_rows' => $preview['summary']['invalid_rows'] ?? 0,
            'duplicate_rows' => $preview['summary']['duplicate_rows'] ?? 0,
            'missing_required_rows' => $preview['summary']['missing_required_rows'] ?? 0,
            'status' => 'previewed',
        ]);

        return $this->success([
            'import' => $this->serializeImport($bulkImport->fresh()),
            ...$preview,
        ], 'Service import preview generated successfully.');
    }

    public function start(Request $request, BulkImport $bulkImport): JsonResponse
    {
        $this->assertImportOwnership($request, $bulkImport);
        abort_unless($bulkImport->module === 'service', 404);

        if (blank($bulkImport->mapping)) {
            return response()->json([
                'success' => false,
                'message' => 'Mapping must be completed before import can begin.',
            ], 422);
        }

        $bulkImport->update([
            'status' => 'queued',
            'started_at' => now(),
            'last_error' => null,
        ]);

        $totalRows = (int) ($bulkImport->total_rows ?: 0);
        $shouldProcessInline = app()->environment('local', 'testing') || ($totalRows > 0 && $totalRows <= 25);

        if ($shouldProcessInline) {
            ProcessServiceImportJob::dispatchSync($bulkImport->id);
        } else {
            ProcessServiceImportJob::dispatch($bulkImport->id)->onQueue('imports');
        }

        return $this->success([
            'import' => $this->serializeImport($bulkImport->fresh()),
        ], 'Service import started successfully.');
    }

    public function show(Request $request, BulkImport $bulkImport): JsonResponse
    {
        $this->assertImportOwnership($request, $bulkImport);
        abort_unless($bulkImport->module === 'service', 404);

        return $this->success([
            'import' => $this->serializeImport($bulkImport->fresh()),
        ], 'Service import retrieved successfully.');
    }

    public function errorReport(Request $request, BulkImport $bulkImport)
    {
        $this->assertImportOwnership($request, $bulkImport);
        abort_unless($bulkImport->module === 'service', 404);

        abort_unless($bulkImport->error_report_path && Storage::disk($bulkImport->stored_disk)->exists($bulkImport->error_report_path), 404);

        return Storage::disk($bulkImport->stored_disk)->download($bulkImport->error_report_path, 'service-import-errors.csv');
    }

    private function assertImportOwnership(Request $request, BulkImport $bulkImport): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->hasRole('super_admin')) {
            return;
        }

        $organizationId = $user->organization_id;
        abort_unless($organizationId && $bulkImport->org_id === $organizationId, 403);
    }

    private function serializeImport(BulkImport $import): array
    {
        return [
            'id' => $import->id,
            'status' => $import->status,
            'original_filename' => $import->original_filename,
            'source_columns' => $import->source_columns ?? [],
            'mapping' => $import->mapping ?? [],
            'preview' => $import->preview ?? [],
            'summary' => $import->summary ?? [],
            'total_rows' => $import->total_rows,
            'valid_rows' => $import->valid_rows,
            'invalid_rows' => $import->invalid_rows,
            'duplicate_rows' => $import->duplicate_rows,
            'missing_required_rows' => $import->missing_required_rows,
            'imported_rows' => $import->imported_rows,
            'failed_rows' => $import->failed_rows,
            'skipped_rows' => $import->skipped_rows,
            'progress' => $import->progress,
            'error_report_available' => (bool) $import->error_report_path,
            'last_error' => $import->last_error,
            'started_at' => $import->started_at?->toISOString(),
            'finished_at' => $import->finished_at?->toISOString(),
            'created_at' => $import->created_at?->toISOString(),
            'updated_at' => $import->updated_at?->toISOString(),
        ];
    }
}
