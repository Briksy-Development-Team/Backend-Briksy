<?php

namespace App\Services\Imports;

use App\Models\BulkImport;
use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\Service;
use App\Services\DynamicIdGeneratorService;
use App\Support\Services\ServiceListingRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ServiceImportService
{
    public function __construct(
        private readonly SpreadsheetImportService $spreadsheet,
        private readonly DynamicIdGeneratorService $idGenerator,
        private readonly ImportFieldMatcher $fieldMatcher,
    ) {
    }

    public function meta(): array
    {
        return [
            'system_fields' => $this->systemFields(),
            'required_fields' => $this->requiredFields(),
            'categories' => $this->categories(),
            'organizations' => Organization::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->values()
                ->all(),
            'organization_types' => OrganizationType::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->values()
                ->all(),
        ];
    }

    public function analyze(BulkImport $import): array
    {
        $absolutePath = Storage::disk($import->stored_disk)->path($import->stored_path);
        $headers = $this->spreadsheet->readHeaders($absolutePath, $import->file_type);
        $suggestions = [];
        $usedFields = [];

        foreach ($headers as $header) {
            $match = $this->fieldMatcher->match($this->systemFields(), $header, $usedFields);
            $suggestions[$header] = $match;

            if (!empty($match['field'])) {
                $usedFields[] = $match['field'];
            }
        }

        return [
            'source_columns' => $headers,
            'required_fields' => $this->requiredFields(),
            'system_fields' => $this->systemFields(),
            'suggested_mapping' => $suggestions,
            'sample_rows' => $this->sampleRows($import, 5),
        ];
    }

    public function preview(BulkImport $import, array $mapping): array
    {
        $this->assertMappingIsValid($mapping);

        $absolutePath = Storage::disk($import->stored_disk)->path($import->stored_path);
        $summary = [
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'duplicate_rows' => 0,
            'missing_required_rows' => 0,
            'missing_required_fields' => [],
        ];
        $invalidRows = [];
        $duplicateRows = [];
        $seenFingerprints = [];

        $rowNumber = 1;
        foreach ($this->spreadsheet->iterateRows($absolutePath, $import->file_type) as $row) {
            $rowNumber++;
            $summary['total_rows']++;

            $payload = $this->normalizeMappedRow($row, $mapping);
            $missingRequired = $this->missingRequiredFields($payload, $this->requiredFields(), $import);

            if ($missingRequired !== []) {
                $summary['invalid_rows']++;
                $summary['missing_required_rows']++;
                $summary['missing_required_fields'] = array_values(array_unique(array_merge($summary['missing_required_fields'], $missingRequired)));
                $invalidRows[] = [
                    'row_number' => $rowNumber,
                    'status' => 'missing_required_fields',
                    'errors' => array_map(static fn (string $field): string => ucfirst(str_replace('_', ' ', $field)).' is required', $missingRequired),
                    'values' => $payload,
                ];
                continue;
            }

            $validation = $this->validatePayload($payload);
            if ($validation->fails()) {
                $summary['invalid_rows']++;
                $invalidRows[] = [
                    'row_number' => $rowNumber,
                    'status' => 'invalid',
                    'errors' => Arr::flatten($validation->errors()->all()),
                    'values' => $payload,
                ];
                continue;
            }

            $fingerprint = $this->fingerprint($payload);
            if (isset($seenFingerprints[$fingerprint]) || $this->isDuplicateService($import, $payload)) {
                $summary['duplicate_rows']++;
                $duplicateRows[] = [
                    'row_number' => $rowNumber,
                    'status' => 'duplicate',
                    'values' => $payload,
                ];
                continue;
            }

            $seenFingerprints[$fingerprint] = true;
            $summary['valid_rows']++;
        }

        return [
            'summary' => $summary,
            'invalid_rows' => $invalidRows,
            'duplicate_rows' => $duplicateRows,
        ];
    }

    public function process(BulkImport $import): array
    {
        $mapping = $import->mapping ?? [];
        $this->assertMappingIsValid($mapping);

        $absolutePath = Storage::disk($import->stored_disk)->path($import->stored_path);
        $processed = [
            'valid_rows' => 0,
            'imported_rows' => 0,
            'failed_rows' => 0,
            'missing_required_rows' => 0,
            'skipped_rows' => 0,
            'duplicate_rows' => 0,
            'total_rows' => 0,
        ];
        $failedRows = [];
        $seenFingerprints = [];
        $serviceIds = [];

        foreach ($this->spreadsheet->iterateRows($absolutePath, $import->file_type) as $row) {
            $processed['total_rows']++;
            $payload = $this->normalizeMappedRow($row, $mapping);
            $missingRequired = $this->missingRequiredFields($payload, $this->requiredFields(), $import);

            if ($missingRequired !== []) {
                $processed['failed_rows']++;
                $processed['missing_required_rows']++;
                $failedRows[] = [
                    'row_number' => $processed['total_rows'] + 1,
                    'errors' => array_map(static fn (string $field): string => ucfirst(str_replace('_', ' ', $field)).' is required', $missingRequired),
                    'values' => $payload,
                ];
                $this->updateProgress($import, $processed);
                continue;
            }

            $validation = $this->validatePayload($payload);
            if ($validation->fails()) {
                $processed['failed_rows']++;
                $failedRows[] = [
                    'row_number' => $processed['total_rows'] + 1,
                    'errors' => Arr::flatten($validation->errors()->all()),
                    'values' => $payload,
                ];
                $this->updateProgress($import, $processed);
                continue;
            }

            $fingerprint = $this->fingerprint($payload);
            if (isset($seenFingerprints[$fingerprint]) || $this->isDuplicateService($import, $payload)) {
                $processed['duplicate_rows']++;
                $processed['skipped_rows']++;
                $this->updateProgress($import, $processed);
                continue;
            }

            $seenFingerprints[$fingerprint] = true;
            $processed['valid_rows']++;

            try {
                $service = DB::transaction(function () use ($import, $payload): Service {
                    return Service::query()->create([
                        'organization_id' => $payload['organization_id'] ?? $import->org_id,
                        'type_id' => $payload['type_id'] ?? null,
                        'name' => $payload['name'],
                        'title' => $payload['title'] ?? $payload['name'],
                        'category' => $payload['category'] ?? null,
                        'slug' => $payload['slug'],
                        'generated_id' => $this->idGenerator->generate('services'),
                        'description' => $payload['description'] ?? null,
                        'service_area' => $payload['service_area'] ?? null,
                        'service_area_geometry' => $payload['service_area_geometry'] ?? null,
                        'rate_from' => $payload['rate_from'] ?? null,
                        'rate_to' => $payload['rate_to'] ?? null,
                        'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : true,
                    ]);
                });

                $serviceIds[] = $service->id;
                $processed['imported_rows']++;
            } catch (\Throwable $throwable) {
                $processed['failed_rows']++;
                $failedRows[] = [
                    'row_number' => $processed['total_rows'] + 1,
                    'errors' => [$throwable->getMessage()],
                    'values' => $payload,
                ];
            }

            $this->updateProgress($import, $processed);
        }

        return [
            'summary' => $processed,
            'failed_rows' => $failedRows,
            'service_ids' => $serviceIds,
        ];
    }

    public function buildErrorReport(BulkImport $import, array $failedRows): string
    {
        $directory = "imports/service/{$import->id}";
        $path = "{$directory}/error-report.csv";
        Storage::disk($import->stored_disk)->makeDirectory($directory);
        $handle = fopen(Storage::disk($import->stored_disk)->path($path), 'w');

        if ($handle === false) {
            throw new \RuntimeException('Unable to create error report file.');
        }

        fputcsv($handle, ['row_number', 'errors', 'values']);

        foreach ($failedRows as $row) {
            fputcsv($handle, [
                $row['row_number'] ?? '',
                implode(' | ', $row['errors'] ?? []),
                json_encode($row['values'] ?? [], JSON_THROW_ON_ERROR),
            ]);
        }

        fclose($handle);

        return $path;
    }

    public function fields(): array
    {
        return $this->systemFields();
    }

    public function templateSampleRow(): array
    {
        return [
            'name' => 'Premium Plumbing',
            'title' => 'Premium Plumbing Adelaide',
            'category' => 'plumbing',
            'slug' => 'premium-plumbing',
            'description' => 'Residential and commercial plumbing services.',
            'service_area' => 'Adelaide, SA',
            'service_area_geometry' => '{"type":"Polygon","coordinates":[[[138.55,-34.93],[138.60,-34.93],[138.60,-34.88],[138.55,-34.88],[138.55,-34.93]]]}',
            'rate_from' => 120,
            'rate_to' => 280,
            'is_active' => '1',
            'organization_id' => '',
            'type_id' => '',
        ];
    }

    private function systemFields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name', 'required' => true, 'aliases' => ['name', 'service name', 'title', 'service title']],
            ['key' => 'title', 'label' => 'Title', 'required' => false, 'aliases' => ['title', 'display name', 'service title']],
            ['key' => 'category', 'label' => 'Category', 'required' => false, 'aliases' => ['category', 'service category', 'type']],
            ['key' => 'slug', 'label' => 'Slug', 'required' => true, 'aliases' => ['slug', 'service slug']],
            ['key' => 'description', 'label' => 'Description', 'required' => false, 'aliases' => ['description', 'details', 'summary']],
            ['key' => 'service_area', 'label' => 'Service Area', 'required' => false, 'aliases' => ['service area', 'coverage area', 'region']],
            ['key' => 'service_area_geometry', 'label' => 'Service Area Geometry', 'required' => false, 'aliases' => ['service area geometry', 'coverage geometry', 'geometry', 'polygon']],
            ['key' => 'rate_from', 'label' => 'Rate From', 'required' => false, 'aliases' => ['rate from', 'min rate', 'starting price']],
            ['key' => 'rate_to', 'label' => 'Rate To', 'required' => false, 'aliases' => ['rate to', 'max rate', 'ending price']],
            ['key' => 'is_active', 'label' => 'Active', 'required' => false, 'aliases' => ['active', 'is active', 'status']],
            ['key' => 'organization_id', 'label' => 'Organization', 'required' => false, 'aliases' => ['organization', 'organization id', 'company', 'provider']],
            ['key' => 'type_id', 'label' => 'Organization Type', 'required' => false, 'aliases' => ['organization type', 'type', 'type id', 'provider type']],
        ];
    }

    private function categories(): array
    {
        return ['electrical', 'plumbing', 'fencing', 'landscapers', 'conveyancers', 'brokers'];
    }

    private function requiredFields(): array
    {
        return array_values(array_map(
            static fn (array $field): string => $field['key'],
            array_filter($this->systemFields(), static fn (array $field): bool => (bool) $field['required']),
        ));
    }

    private function normalizeMappedRow(array $row, array $mapping): array
    {
        $payload = [];

        foreach ($row as $column => $value) {
            $field = $mapping[$column] ?? null;
            if (!$field) {
                continue;
            }

            $normalized = $this->normalizeValue($field, $value);

            if ($field === 'organization_id' && is_string($value) && blank($normalized) && filled($value)) {
                $payload['_organization_lookup_failed'] = true;
            }

            if ($field === 'type_id' && is_string($value) && blank($normalized) && filled($value)) {
                $payload['_type_lookup_failed'] = true;
            }

            if ($field === 'service_area_geometry' && is_string($value) && blank($normalized) && filled($value)) {
                $payload['_service_area_geometry_invalid'] = true;
            }

            if ($field === 'is_active' && is_string($value) && $normalized === null && filled($value)) {
                $payload['_is_active_invalid'] = true;
            }

            $payload[$field] = $normalized;
        }

        if (array_key_exists('slug', $payload) && blank($payload['slug']) && !blank($payload['name'])) {
            $payload['slug'] = Str::slug((string) $payload['name']);
        }

        if (!array_key_exists('title', $payload) || blank($payload['title'])) {
            $payload['title'] = $payload['name'] ?? null;
        }

        return $payload;
    }

    private function normalizeValue(string $field, mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '') {
            return null;
        }

        return match ($field) {
            'rate_from', 'rate_to' => is_numeric($value) ? (float) $value : $value,
            'is_active' => $this->normalizeBoolean($value),
            'service_area_geometry' => $this->normalizeGeometry($value),
            'organization_id' => $this->resolveOrganizationId((string) $value),
            'type_id' => $this->resolveTypeId((string) $value),
            default => $value,
        };
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes', 'y', 'on', 'active' => true,
            '0', 'false', 'no', 'n', 'off', 'inactive' => false,
            default => null,
        };
    }

    private function normalizeGeometry(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function resolveOrganizationId(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (Str::isUuid($trimmed)) {
            return Organization::query()->whereKey($trimmed)->value('id');
        }

        $normalized = $this->normalizeHeader($trimmed);

        $organization = Organization::query()
            ->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(name, ' ', ''), '_', ''), '-', '')) = ?", [$normalized])
            ->orWhereRaw("LOWER(REPLACE(REPLACE(REPLACE(slug, ' ', ''), '_', ''), '-', '')) = ?", [$normalized])
            ->first(['id']);

        return $organization?->id;
    }

    private function resolveTypeId(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (Str::isUuid($trimmed)) {
            return OrganizationType::query()->whereKey($trimmed)->value('id');
        }

        $normalized = $this->normalizeHeader($trimmed);

        $type = OrganizationType::query()
            ->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(name, ' ', ''), '_', ''), '-', '')) = ?", [$normalized])
            ->orWhereRaw("LOWER(REPLACE(REPLACE(REPLACE(slug, ' ', ''), '_', ''), '-', '')) = ?", [$normalized])
            ->first(['id']);

        return $type?->id;
    }

    private function validatePayload(array $payload)
    {
        $organizationLookupFailed = (bool) ($payload['_organization_lookup_failed'] ?? false);
        $typeLookupFailed = (bool) ($payload['_type_lookup_failed'] ?? false);
        $serviceAreaGeometryInvalid = (bool) ($payload['_service_area_geometry_invalid'] ?? false);
        $isActiveInvalid = (bool) ($payload['_is_active_invalid'] ?? false);

        unset(
            $payload['_organization_lookup_failed'],
            $payload['_type_lookup_failed'],
            $payload['_service_area_geometry_invalid'],
            $payload['_is_active_invalid'],
        );

        if (array_key_exists('organization_id', $payload) && blank($payload['organization_id'])) {
            unset($payload['organization_id']);
        }
        if (array_key_exists('type_id', $payload) && blank($payload['type_id'])) {
            unset($payload['type_id']);
        }

        $validator = Validator::make($payload, ServiceListingRules::store());
        $validator->after(function ($validator) use (
            $organizationLookupFailed,
            $typeLookupFailed,
            $serviceAreaGeometryInvalid,
            $isActiveInvalid
        ): void {
            if ($organizationLookupFailed) {
                $validator->errors()->add('organization_id', 'Organization not found.');
            }

            if ($typeLookupFailed) {
                $validator->errors()->add('type_id', 'Organization Type not found.');
            }

            if ($serviceAreaGeometryInvalid) {
                $validator->errors()->add('service_area_geometry', 'Service area geometry must be valid JSON.');
            }

            if ($isActiveInvalid) {
                $validator->errors()->add('is_active', 'Active must be a valid boolean value.');
            }
        });

        return $validator;
    }

    private function missingRequiredFields(array $payload, array $requiredFields, BulkImport $import): array
    {
        $missing = [];

        foreach ($requiredFields as $field) {
            if ($field === 'organization_id' && blank($import->org_id)) {
                continue;
            }

            if (!array_key_exists($field, $payload) || blank($payload[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    private function fingerprint(array $payload): string
    {
        return strtolower(trim(implode('|', [
            (string) ($payload['slug'] ?? $payload['name'] ?? ''),
            (string) ($payload['organization_id'] ?? ''),
        ])));
    }

    private function isDuplicateService(BulkImport $import, array $payload): bool
    {
        $slug = $payload['slug'] ?? null;
        if (blank($slug)) {
            return false;
        }

        return Service::query()
            ->where('slug', $slug)
            ->when(filled($payload['organization_id'] ?? $import->org_id), function (Builder $query) use ($payload, $import): void {
                $query->where('organization_id', $payload['organization_id'] ?? $import->org_id);
            })
            ->exists();
    }

    private function sampleRows(BulkImport $import, int $limit): array
    {
        $absolutePath = Storage::disk($import->stored_disk)->path($import->stored_path);
        $rows = [];
        $count = 0;

        foreach ($this->spreadsheet->iterateRows($absolutePath, $import->file_type) as $row) {
            $rows[] = $row;
            if (++$count >= $limit) {
                break;
            }
        }

        return $rows;
    }

    private function assertMappingIsValid(array $mapping): void
    {
        $allowedFields = array_values(array_map(
            static fn (array $field): string => $field['key'],
            $this->systemFields(),
        ));
        $requiredFields = $this->requiredFields();
        $selectedFields = array_values(array_filter(array_map(static fn ($field) => is_string($field) ? $field : null, $mapping)));
        $invalidFields = array_values(array_filter($selectedFields, static fn (string $field) => !in_array($field, $allowedFields, true)));

        if ($invalidFields !== []) {
            throw ValidationException::withMessages([
                'mapping' => 'Invalid system field selected in mapping.',
            ]);
        }

        if (count(array_unique($selectedFields)) !== count($selectedFields)) {
            throw ValidationException::withMessages([
                'mapping' => 'Each system field can only be mapped once.',
            ]);
        }

        foreach ($requiredFields as $field) {
            if (!in_array($field, $selectedFields, true)) {
                throw ValidationException::withMessages([
                    'mapping' => ucfirst(str_replace('_', ' ', $field)).' must be mapped before continuing.',
                ]);
            }
        }
    }

    private function updateProgress(BulkImport $import, array $processed): void
    {
        $total = max(1, (int) ($import->total_rows ?: $processed['total_rows']));
        $done = $processed['imported_rows'] + $processed['failed_rows'] + $processed['skipped_rows'];
        $progress = min(99, (int) round(($done / $total) * 100));

        $import->update([
            'progress' => $progress,
            'imported_rows' => $processed['imported_rows'],
            'failed_rows' => $processed['failed_rows'],
            'missing_required_rows' => $processed['missing_required_rows'],
            'skipped_rows' => $processed['skipped_rows'],
            'duplicate_rows' => $processed['duplicate_rows'],
            'valid_rows' => $processed['valid_rows'],
            'total_rows' => $processed['total_rows'],
        ]);
    }
}
