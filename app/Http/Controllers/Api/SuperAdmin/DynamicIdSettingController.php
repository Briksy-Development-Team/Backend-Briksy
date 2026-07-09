<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\DynamicIdSettingStoreRequest;
use App\Http\Requests\Api\SuperAdmin\DynamicIdSettingUpdateRequest;
use App\Http\Resources\SuperAdmin\DynamicIdSettingResource;
use App\Models\ActivityLog;
use App\Models\DynamicIdSetting;
use App\Services\DynamicIdGeneratorService;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DynamicIdSettingController extends Controller
{
    public function __construct(private readonly DynamicIdGeneratorService $idGenerator)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = DynamicIdSetting::query();
        ApiQueryBuilder::applySearch($query, $request->string('search')->toString() ?: null, ['entity_type', 'prefix', 'reset_frequency']);
        ApiQueryBuilder::applySort($query, $request->string('sort')->toString() ?: null, $request->string('direction')->toString() ?: 'desc', [
            'created_at' => 'created_at',
            'entity_type' => 'entity_type',
        ], 'created_at');

        $items = $query->paginate(ApiQueryBuilder::normalizePerPage($request->integer('per_page')))->withQueryString();

        return $this->paginated(
            DynamicIdSettingResource::collection($items)->resolve(),
            $items,
            'Dynamic ID settings retrieved successfully.'
        );
    }

    public function store(DynamicIdSettingStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $setting = DB::transaction(function () use ($validated, $request): DynamicIdSetting {
            $setting = DynamicIdSetting::query()->create($validated);
            $this->logChange($request, $setting, null, $this->snapshot($setting), 'created');

            return $setting;
        });

        return $this->created(new DynamicIdSettingResource($setting), 'Dynamic ID setting created successfully.');
    }

    public function show(DynamicIdSetting $dynamicIdSetting): JsonResponse
    {
        return $this->success(new DynamicIdSettingResource($dynamicIdSetting), 'Dynamic ID setting retrieved successfully.');
    }

    public function update(DynamicIdSettingUpdateRequest $request, DynamicIdSetting $dynamicIdSetting): JsonResponse
    {
        $validated = $request->validated();
        $original = $this->snapshot($dynamicIdSetting);

        $nextPrefix = array_key_exists('prefix', $validated) ? $validated['prefix'] : $dynamicIdSetting->prefix;
        $nextCurrentNumber = array_key_exists('current_number', $validated)
            ? (int) $validated['current_number']
            : (int) $dynamicIdSetting->current_number;
        $nextStartingNumber = array_key_exists('starting_number', $validated)
            ? (int) $validated['starting_number']
            : (int) $dynamicIdSetting->starting_number;

        $prefixChanged = $nextPrefix !== $dynamicIdSetting->prefix;
        $currentDecreased = $nextCurrentNumber < (int) $dynamicIdSetting->current_number;
        $startingDecreased = $nextStartingNumber < (int) $dynamicIdSetting->starting_number;

        if (!$prefixChanged && ($currentDecreased || $startingDecreased) && !($validated['confirm_counter_reset'] ?? false)) {
            throw ValidationException::withMessages([
                'confirm_counter_reset' => 'Counter resets are blocked unless you explicitly confirm them or change the prefix.',
            ]);
        }

        $setting = DB::transaction(function () use ($dynamicIdSetting, $validated, $request, $original): DynamicIdSetting {
            $dynamicIdSetting->fill($validated);
            $dynamicIdSetting->save();

            $this->logChange($request, $dynamicIdSetting, $original, $this->snapshot($dynamicIdSetting), 'updated');

            return $dynamicIdSetting;
        });

        return $this->success(new DynamicIdSettingResource($setting), 'Dynamic ID setting updated successfully.');
    }

    public function destroy(Request $request, DynamicIdSetting $dynamicIdSetting): JsonResponse
    {
        $original = $this->snapshot($dynamicIdSetting);

        DB::transaction(function () use ($dynamicIdSetting, $request, $original): void {
            $dynamicIdSetting->delete();

            $this->logChange($request, $dynamicIdSetting, $original, null, 'deleted');
        });

        return $this->success([], 'Dynamic ID setting deleted successfully.');
    }

    public function preview(string $entityType): JsonResponse
    {
        return $this->success([
            'entity_type' => $entityType,
            'sample_preview' => $this->idGenerator->sampleForEntity($entityType),
        ], 'Dynamic ID preview retrieved successfully.');
    }

    private function snapshot(?DynamicIdSetting $setting): ?array
    {
        if (!$setting) {
            return null;
        }

        return [
            'entity_type' => $setting->entity_type,
            'prefix' => $setting->prefix,
            'number_padding' => (int) $setting->number_padding,
            'starting_number' => (int) $setting->starting_number,
            'current_number' => (int) $setting->current_number,
            'reset_frequency' => $setting->reset_frequency,
            'is_active' => (bool) $setting->is_active,
        ];
    }

    private function logChange(Request $request, DynamicIdSetting $setting, ?array $oldValues, ?array $newValues, string $action): void
    {
        ActivityLog::query()->create([
            'causer_id' => $request->user()?->id,
            'subject_id' => $setting->id,
            'organization_id' => null,
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
            'user_email' => $request->user()?->email,
            'user_role' => $request->user()?->isSuperAdmin() ? 'super_admin' : ($request->user()?->hasRole('super_admin_employee') ? 'super_admin_employee' : null),
            'action' => $action,
            'module' => 'dynamic_id_settings',
            'description' => sprintf('Dynamic ID setting %s for %s.', $action, $setting->entity_type),
            'method' => $request->method(),
            'route' => $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => [
                'module' => $setting->entity_type,
                'old_prefix' => $oldValues['prefix'] ?? null,
                'new_prefix' => $newValues['prefix'] ?? null,
                'old_padding' => $oldValues['number_padding'] ?? null,
                'new_padding' => $newValues['number_padding'] ?? null,
                'old_current_number' => $oldValues['current_number'] ?? null,
                'new_current_number' => $newValues['current_number'] ?? null,
                'user_id' => $request->user()?->id,
                'changed_at' => now()->toISOString(),
            ],
        ]);
    }
}
