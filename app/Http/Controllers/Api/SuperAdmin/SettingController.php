<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\CompanySettingRequest;
use App\Http\Requests\Api\SuperAdmin\PlatformSettingRequest;
use App\Http\Resources\SuperAdmin\CompanySettingResource;
use App\Http\Resources\SuperAdmin\PlatformSettingResource;
use App\Models\CompanySetting;
use App\Models\PlatformSetting;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $settings = PlatformSetting::query()->orderBy('group')->orderBy('key')->get();
        return $this->success(PlatformSettingResource::collection($settings)->resolve(), 'Platform settings retrieved successfully.');
    }

    public function publicSettings(): JsonResponse
    {
        $settings = PlatformSetting::query()->where('is_public', true)->orderBy('group')->orderBy('key')->get();
        return $this->success(PlatformSettingResource::collection($settings)->resolve(), 'Public platform settings retrieved successfully.');
    }

    public function update(PlatformSettingRequest $request): JsonResponse
    {
        foreach ($request->validated()['settings'] as $setting) {
            PlatformSetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => is_array($setting['value'] ?? null) ? json_encode($setting['value']) : ($setting['value'] ?? null),
                    'type' => $setting['type'],
                    'group' => $setting['group'] ?? null,
                    'label' => $setting['label'] ?? null,
                    'is_public' => $setting['is_public'] ?? false,
                ]
            );
        }

        return $this->success($this->getSettingsPayload(), 'Platform settings updated successfully.');
    }

    public function companyIndex(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;
        abort_unless($organizationId, 403, 'Admin account is not assigned to an organization.');

        $settings = CompanySetting::query()->where('organization_id', $organizationId)->orderBy('group')->orderBy('key')->get();

        return $this->success(CompanySettingResource::collection($settings)->resolve(), 'Company settings retrieved successfully.');
    }

    public function companyUpdate(CompanySettingRequest $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;
        abort_unless($organizationId, 403, 'Admin account is not assigned to an organization.');

        foreach ($request->validated()['settings'] as $setting) {
            CompanySetting::query()->updateOrCreate(
                ['organization_id' => $organizationId, 'key' => $setting['key']],
                [
                    'value' => is_array($setting['value'] ?? null) ? json_encode($setting['value']) : ($setting['value'] ?? null),
                    'type' => $setting['type'],
                    'group' => $setting['group'] ?? null,
                    'label' => $setting['label'] ?? null,
                ]
            );
        }

        return $this->success($this->companySettingsPayload($organizationId), 'Company settings updated successfully.');
    }

    protected function getSettingsPayload(): array
    {
        return PlatformSetting::query()->orderBy('group')->orderBy('key')->get()->map(fn (PlatformSetting $setting): array => (new PlatformSettingResource($setting))->toArray(request()))->values()->all();
    }

    protected function companySettingsPayload(string $organizationId): array
    {
        return CompanySetting::query()->where('organization_id', $organizationId)->orderBy('group')->orderBy('key')->get()->map(fn (CompanySetting $setting): array => (new CompanySettingResource($setting))->toArray(request()))->values()->all();
    }
}
