<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\CompanySettingRequest;
use App\Http\Resources\SuperAdmin\CompanySettingResource;
use App\Models\CompanySetting;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;
        abort_unless($organizationId, 403, 'Admin account is not assigned to an organization.');

        $settings = CompanySetting::query()->where('organization_id', $organizationId)->orderBy('group')->orderBy('key')->get();

        return $this->success(CompanySettingResource::collection($settings)->resolve(), 'Company settings retrieved successfully.');
    }

    public function update(CompanySettingRequest $request): JsonResponse
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

        $this->notificationService->notifySuperAdmins(
            $this->notificationService->buildPayload(
                'system_setting_changed',
                'Company settings updated',
                sprintf('Company settings were updated for organisation %s.', $organizationId),
                CompanySetting::class,
                $organizationId,
                '/super-admin/settings',
                'normal',
                $request->user()?->id,
                $organizationId
            ),
            'Company settings updated',
            'Review settings'
        );

        $settings = CompanySetting::query()->where('organization_id', $organizationId)->orderBy('group')->orderBy('key')->get();

        return $this->success(CompanySettingResource::collection($settings)->resolve(), 'Company settings updated successfully.');
    }
}
