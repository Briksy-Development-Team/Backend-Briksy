<?php

namespace App\Services\Imports;

use App\Models\PropertyImport;
use App\Models\PropertyListing;
use App\Models\PropertyType;
use App\Services\DynamicIdGeneratorService;
use App\Support\Properties\PropertyListingRules;
use App\Support\Properties\PropertyWorkflow;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PropertyImportService
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
            'property_types' => PropertyType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug'])
                ->values()
                ->all(),
        ];
    }

    public function analyze(PropertyImport $import): array
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

    public function preview(PropertyImport $import, array $mapping): array
    {
        $this->assertMappingIsValid($mapping);

        $absolutePath = Storage::disk($import->stored_disk)->path($import->stored_path);
        $fileType = $import->file_type;

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
        $requiredFields = $this->requiredFields();

        $rowNumber = 1;
        foreach ($this->spreadsheet->iterateRows($absolutePath, $fileType) as $row) {
            $rowNumber++;
            $summary['total_rows']++;

            $payload = $this->normalizeMappedRow($row, $mapping);
            $missingRequired = $this->missingRequiredFields($payload, $requiredFields);

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

            if (isset($seenFingerprints[$fingerprint]) || $this->isDuplicateListing($import, $payload)) {
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

    public function process(PropertyImport $import): array
    {
        $mapping = $import->mapping ?? [];
        $this->assertMappingIsValid($mapping);

        $absolutePath = Storage::disk($import->stored_disk)->path($import->stored_path);
        $requiredFields = $this->requiredFields();
        $rowNumber = 1;
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
        $propertyIds = [];

        foreach ($this->spreadsheet->iterateRows($absolutePath, $import->file_type) as $row) {
            $rowNumber++;
            $processed['total_rows']++;

            $payload = $this->normalizeMappedRow($row, $mapping);
            $missingRequired = $this->missingRequiredFields($payload, $requiredFields);

            if ($missingRequired !== []) {
                $processed['failed_rows']++;
                $processed['missing_required_rows']++;
                $failedRows[] = [
                    'row_number' => $rowNumber,
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
                    'row_number' => $rowNumber,
                    'errors' => Arr::flatten($validation->errors()->all()),
                    'values' => $payload,
                ];
                $this->updateProgress($import, $processed);
                continue;
            }

            $fingerprint = $this->fingerprint($payload);
            if (isset($seenFingerprints[$fingerprint]) || $this->isDuplicateListing($import, $payload)) {
                $processed['duplicate_rows']++;
                $processed['skipped_rows']++;
                $this->updateProgress($import, $processed);
                continue;
            }

            $seenFingerprints[$fingerprint] = true;
            $processed['valid_rows']++;

            try {
                $listing = DB::transaction(function () use ($import, $payload): PropertyListing {
                    return PropertyListing::query()->create([
                        'org_id' => $import->org_id,
                        'creator_id' => $import->created_by,
                        'generated_id' => $this->idGenerator->generate('properties'),
                        'property_type_id' => $payload['property_type_id'] ?? null,
                        'avg_prop_rating' => 0,
                        'address_line_1' => $payload['address_line_1'] ?? ($payload['address'] ?? null),
                        'address_line_2' => $payload['address_line_2'] ?? null,
                        'latitude' => $payload['latitude'] ?? null,
                        'longitude' => $payload['longitude'] ?? null,
                        'title' => $payload['title'],
                        'description' => $payload['description'] ?? null,
                        'address' => $payload['address'] ?? null,
                        'full_address' => $payload['full_address'] ?? ($payload['address'] ?? null),
                        'formatted_address' => $payload['formatted_address'] ?? ($payload['full_address'] ?? null),
                        'place_id' => $payload['place_id'] ?? null,
                        'status' => PropertyWorkflow::STATUS_PENDING_REVIEW,
                        'suburb' => $payload['suburb'] ?? null,
                        'state' => $payload['state'] ?? null,
                        'postcode' => $payload['postcode'] ?? null,
                        'country' => $payload['country'] ?? 'Australia',
                        'submitted_at' => now(),
                        'location_verified' => false,
                        'location_verified_by' => null,
                        'location_verified_at' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'rejection_reason' => null,
                        'published_at' => null,
                    ]);
                });

                $propertyIds[] = $listing->id;
                $processed['imported_rows']++;
            } catch (\Throwable $throwable) {
                $processed['failed_rows']++;
                $failedRows[] = [
                    'row_number' => $rowNumber,
                    'errors' => [$throwable->getMessage()],
                    'values' => $payload,
                ];
            }

            $this->updateProgress($import, $processed);
        }

        return [
            'summary' => $processed,
            'failed_rows' => $failedRows,
            'property_ids' => $propertyIds,
        ];
    }

    public function buildErrorReport(PropertyImport $import, array $failedRows): string
    {
        $directory = "imports/property/{$import->id}";
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
            'title' => 'Modern Family Home',
            'description' => 'A modern three-bedroom family home with renovated kitchen.',
            'property_type_id' => '',
            'address_line_1' => '123 Sample Street',
            'address_line_2' => 'Unit 4',
            'address' => '123 Sample Street, Adelaide SA 5000',
            'full_address' => '123 Sample Street, Adelaide SA 5000',
            'formatted_address' => '123 Sample Street, Adelaide SA 5000, Australia',
            'place_id' => '',
            'suburb' => 'Adelaide',
            'state' => 'SA',
            'postcode' => '5000',
            'country' => 'Australia',
            'latitude' => -34.9285,
            'longitude' => 138.6007,
        ];
    }

    private function systemFields(): array
    {
        return [
            ['key' => 'title', 'label' => 'Property Name', 'required' => true, 'aliases' => ['title', 'name', 'property name', 'property title', 'listing name']],
            ['key' => 'description', 'label' => 'Description', 'required' => false, 'aliases' => ['description', 'details', 'summary']],
            ['key' => 'property_type_id', 'label' => 'Property Type', 'required' => false, 'aliases' => ['property type', 'type', 'property_type', 'property type id']],
            ['key' => 'address_line_1', 'label' => 'Address Line 1', 'required' => false, 'aliases' => ['address line 1', 'address 1', 'street address', 'address']],
            ['key' => 'address_line_2', 'label' => 'Address Line 2', 'required' => false, 'aliases' => ['address line 2', 'address 2', 'suite', 'unit']],
            ['key' => 'address', 'label' => 'Address', 'required' => false, 'aliases' => ['address', 'property address', 'location address']],
            ['key' => 'full_address', 'label' => 'Full Address', 'required' => false, 'aliases' => ['full address', 'property address', 'address full', 'address']],
            ['key' => 'formatted_address', 'label' => 'Formatted Address', 'required' => false, 'aliases' => ['formatted address', 'google formatted address']],
            ['key' => 'place_id', 'label' => 'Place ID', 'required' => false, 'aliases' => ['place id', 'google place id']],
            ['key' => 'suburb', 'label' => 'Suburb', 'required' => false, 'aliases' => ['suburb', 'city', 'town']],
            ['key' => 'state', 'label' => 'State', 'required' => false, 'aliases' => ['state', 'province', 'region']],
            ['key' => 'postcode', 'label' => 'Postcode', 'required' => false, 'aliases' => ['postcode', 'postal code', 'zip']],
            ['key' => 'country', 'label' => 'Country', 'required' => false, 'aliases' => ['country', 'nation']],
            ['key' => 'latitude', 'label' => 'Latitude', 'required' => false, 'aliases' => ['latitude', 'lat']],
            ['key' => 'longitude', 'label' => 'Longitude', 'required' => false, 'aliases' => ['longitude', 'lng', 'lon', 'long']],
        ];
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

            if ($field === 'property_type_id' && $normalized !== null) {
                $resolvedPropertyTypeId = $this->resolvePropertyTypeId((string) $normalized);

                if ($resolvedPropertyTypeId === null) {
                    $payload['_property_type_lookup_failed'] = true;
                    $normalized = null;
                } else {
                    $normalized = $resolvedPropertyTypeId;
                }
            }

            $payload[$field] = $normalized;
        }

        if (array_key_exists('address', $payload) && !array_key_exists('full_address', $payload)) {
            $payload['full_address'] = $payload['address'];
        }

        if (array_key_exists('formatted_address', $payload) && !array_key_exists('full_address', $payload)) {
            $payload['full_address'] = $payload['formatted_address'];
        }

        if (array_key_exists('address_line_1', $payload) && !array_key_exists('address', $payload)) {
            $payload['address'] = $payload['address_line_1'];
        }

        if (!array_key_exists('country', $payload) || blank($payload['country'])) {
            $payload['country'] = 'Australia';
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
            'latitude', 'longitude' => $value !== null && $value !== '' ? (float) $value : null,
            'location_verified' => $this->normalizeBoolean($value),
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
            '1', 'true', 'yes', 'y', 'on', 'verified' => true,
            '0', 'false', 'no', 'n', 'off', 'pending' => false,
            default => null,
        };
    }

    private function resolvePropertyTypeId(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (Str::isUuid($trimmed)) {
            return PropertyType::query()->whereKey($trimmed)->value('id');
        }

        $normalized = $this->normalizeHeader($trimmed);

        $propertyType = PropertyType::query()
            ->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(name, ' ', ''), '_', ''), '-', '')) = ?", [$normalized])
            ->orWhereRaw("LOWER(REPLACE(REPLACE(REPLACE(slug, ' ', ''), '_', ''), '-', '')) = ?", [$normalized])
            ->first(['id']);

        return $propertyType?->id;
    }

    private function validatePayload(array $payload)
    {
        $lookupFailed = (bool) ($payload['_property_type_lookup_failed'] ?? false);
        unset($payload['_property_type_lookup_failed']);

        $validator = Validator::make($payload, PropertyListingRules::store());

        $validator->after(static function ($validator) use ($lookupFailed): void {
            if ($lookupFailed) {
                $validator->errors()->add('property_type_id', 'Property Type not found.');
            }
        });

        return $validator;
    }

    private function missingRequiredFields(array $payload, array $requiredFields): array
    {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $payload) || blank($payload[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    private function fingerprint(array $payload): string
    {
        return strtolower(trim(implode('|', [
            (string) ($payload['title'] ?? ''),
            (string) ($payload['address_line_1'] ?? $payload['address'] ?? ''),
            (string) ($payload['postcode'] ?? ''),
            (string) ($payload['suburb'] ?? ''),
        ])));
    }

    private function isDuplicateListing(PropertyImport $import, array $payload): bool
    {
        $title = $payload['title'] ?? null;
        if (blank($title)) {
            return false;
        }

        $query = PropertyListing::query()
            ->where('org_id', $import->org_id)
            ->where('title', $title);

        $address = $payload['address_line_1'] ?? $payload['address'] ?? null;

        if (filled($address)) {
            $query->where(function ($addressQuery) use ($address): void {
                $addressQuery->where('address_line_1', $address)
                    ->orWhere('address', $address)
                    ->orWhere('full_address', $address);
            });
        }

        return $query->exists();
    }

    private function sampleRows(PropertyImport $import, int $limit): array
    {
        $absolutePath = Storage::disk($import->stored_disk)->path($import->stored_path);
        $rows = [];
        $count = 0;

        foreach ($this->spreadsheet->iterateRows($absolutePath, $import->file_type) as $row) {
            $rows[] = $row;
            $count++;

            if ($count >= $limit) {
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

    private function updateProgress(PropertyImport $import, array $processed): void
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
