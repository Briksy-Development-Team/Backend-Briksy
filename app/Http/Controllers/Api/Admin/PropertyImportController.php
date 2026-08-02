<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Admin\PropertyImportAnalyzeRequest;
use App\Http\Requests\Api\Admin\PropertyImportPreviewRequest;
use App\Jobs\ProcessPropertyImportJob;
use App\Models\PropertyImport;
use App\Services\Imports\ImportTemplateService;
use App\Services\Imports\PropertyImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PropertyImportController extends Controller
{
    public function __construct(
        private readonly PropertyImportService $propertyImportService,
        private readonly ImportTemplateService $templateService,
    ) {
    }

    public function meta(): JsonResponse
    {
        return $this->success(
            $this->propertyImportService->meta(),
            'Property import metadata retrieved successfully.'
        );
    }

    public function template(Request $request)
    {
        $format = strtolower($request->string('format', 'xlsx')->toString());
        $path = $this->templateService->build(
            $this->propertyImportService->fields(),
            $this->propertyImportService->templateSampleRow(),
            $format === 'csv' ? 'csv' : 'xlsx',
            'Properties',
            'property-import-template'
        );

        $downloadName = $format === 'csv' ? 'property-import-template.csv' : 'property-import-template.xlsx';

        return response()->download($path, $downloadName)->deleteFileAfterSend(true);
    }

    public function analyze(PropertyImportAnalyzeRequest $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;
        abort_unless($organizationId, 403, 'Admin account is not assigned to an organization.');

        $file = $request->file('file');
        $extension = strtolower($file?->getClientOriginalExtension() ?: $file?->extension() ?: 'csv');
        $directory = "imports/property/".Str::uuid();
        $storedPath = $file?->storeAs($directory, 'source.'.$extension, 'local');

        if (!$file || !$storedPath) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to store import file.',
            ], 422);
        }

        $import = PropertyImport::query()->create([
            'org_id' => $organizationId,
            'created_by' => $request->user()?->id,
            'module' => 'property',
            'status' => 'analyzed',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'stored_disk' => 'local',
            'file_type' => $extension === 'csv' || $extension === 'txt' ? 'csv' : 'xlsx',
        ]);

        $analysis = $this->propertyImportService->analyze($import);

        $import->update([
            'source_columns' => $analysis['source_columns'],
            'preview' => $analysis,
        ]);

        return $this->success([
            'import' => $this->serializeImport($import->fresh()),
            ...$analysis,
            ...$this->propertyImportService->meta(),
        ], 'Property import file analyzed successfully.');
    }

    public function preview(PropertyImportPreviewRequest $request, PropertyImport $propertyImport): JsonResponse
    {
        $this->assertImportOwnership($request, $propertyImport);

        $mapping = $request->input('mapping', []);
        $preview = $this->propertyImportService->preview($propertyImport, $mapping);

        $propertyImport->update([
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
            'import' => $this->serializeImport($propertyImport->fresh()),
            ...$preview,
        ], 'Property import preview generated successfully.');
    }

    public function start(Request $request, PropertyImport $propertyImport): JsonResponse
    {
        $this->assertImportOwnership($request, $propertyImport);

        if (blank($propertyImport->mapping)) {
            return response()->json([
                'success' => false,
                'message' => 'Mapping must be completed before import can begin.',
            ], 422);
        }

        $propertyImport->update([
            'status' => 'queued',
            'started_at' => now(),
            'last_error' => null,
        ]);

        $totalRows = (int) ($propertyImport->total_rows ?: 0);
        $shouldProcessInline = app()->environment('local', 'testing') || ($totalRows > 0 && $totalRows <= 25);

        if ($shouldProcessInline) {
            ProcessPropertyImportJob::dispatchSync($propertyImport->id);
        } else {
            ProcessPropertyImportJob::dispatch($propertyImport->id)->onQueue('imports');
        }

        return $this->success([
            'import' => $this->serializeImport($propertyImport->fresh()),
        ], 'Property import started successfully.');
    }

    public function show(Request $request, PropertyImport $propertyImport): JsonResponse
    {
        $this->assertImportOwnership($request, $propertyImport);

        return $this->success([
            'import' => $this->serializeImport($propertyImport->fresh()),
        ], 'Property import retrieved successfully.');
    }

    public function errorReport(Request $request, PropertyImport $propertyImport)
    {
        $this->assertImportOwnership($request, $propertyImport);

        abort_unless($propertyImport->error_report_path && Storage::disk($propertyImport->stored_disk)->exists($propertyImport->error_report_path), 404);

        return Storage::disk($propertyImport->stored_disk)->download($propertyImport->error_report_path, 'property-import-errors.csv');
    }

    private function assertImportOwnership(Request $request, PropertyImport $propertyImport): void
    {
        $organizationId = $request->user()?->organization_id;
        abort_unless($organizationId && $propertyImport->org_id === $organizationId, 403);
    }

    private function serializeImport(PropertyImport $import): array
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
