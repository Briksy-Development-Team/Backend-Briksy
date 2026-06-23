<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\NotificationPreferenceResource;
use App\Models\NotificationPreference;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $preference = $this->resolvePreference($request);

        return $this->success(
            new NotificationPreferenceResource($preference),
            'Notification preferences retrieved successfully.'
        );
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'in_app_enabled' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'type_preferences' => ['sometimes', 'array'],
        ]);

        $preference = $this->resolvePreference($request);
        $preference->fill($validated);
        $preference->save();

        return $this->success(
            new NotificationPreferenceResource($preference->fresh()),
            'Notification preferences updated successfully.'
        );
    }

    private function resolvePreference(Request $request): NotificationPreference
    {
        $user = $request->user();

        abort_unless($user, 401);

        return NotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'in_app_enabled' => $this->notificationService->notificationPreferenceDefaults()['in_app_enabled'],
                'email_enabled' => $this->notificationService->notificationPreferenceDefaults()['email_enabled'],
                'type_preferences' => $this->notificationService->notificationPreferenceDefaults()['type_preferences'],
            ]
        );
    }
}
