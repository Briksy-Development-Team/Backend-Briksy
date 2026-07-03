<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\DynamicIdSettingStoreRequest;
use App\Http\Requests\Api\SuperAdmin\DynamicIdSettingUpdateRequest;
use App\Http\Resources\SuperAdmin\DynamicIdSettingResource;
use App\Models\DynamicIdSetting;
use App\Services\DynamicIdGeneratorService;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $setting = DynamicIdSetting::query()->create($request->validated());

        return $this->created(new DynamicIdSettingResource($setting), 'Dynamic ID setting created successfully.');
    }

    public function show(DynamicIdSetting $dynamicIdSetting): JsonResponse
    {
        return $this->success(new DynamicIdSettingResource($dynamicIdSetting), 'Dynamic ID setting retrieved successfully.');
    }

    public function update(DynamicIdSettingUpdateRequest $request, DynamicIdSetting $dynamicIdSetting): JsonResponse
    {
        $dynamicIdSetting->fill($request->validated());
        $dynamicIdSetting->save();

        return $this->success(new DynamicIdSettingResource($dynamicIdSetting), 'Dynamic ID setting updated successfully.');
    }

    public function destroy(DynamicIdSetting $dynamicIdSetting): JsonResponse
    {
        $dynamicIdSetting->delete();

        return $this->success([], 'Dynamic ID setting deleted successfully.');
    }

    public function preview(string $entityType): JsonResponse
    {
        return $this->success([
            'entity_type' => $entityType,
            'sample_preview' => $this->idGenerator->sampleForEntity($entityType),
        ], 'Dynamic ID preview retrieved successfully.');
    }
}
