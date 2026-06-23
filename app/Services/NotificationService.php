<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    public function notifySuperAdmins(array $payload, ?string $mailSubject = null, ?string $mailCtaLabel = null): void
    {
        $users = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'super_admin'))
            ->get();

        $this->notifyUsers($users, $payload, $mailSubject, $mailCtaLabel);
    }

    public function notifyAdminsForOrganisation(string $organisationId, array $payload, ?string $mailSubject = null, ?string $mailCtaLabel = null): void
    {
        $users = User::query()
            ->where('organization_id', $organisationId)
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['admin', 'admin_staff']))
            ->get();

        $this->notifyUsers($users, $payload, $mailSubject, $mailCtaLabel);
    }

    public function notifyUser(User $user, array $payload, ?string $mailSubject = null, ?string $mailCtaLabel = null): void
    {
        $this->notifyUsers(collect([$user]), $payload, $mailSubject, $mailCtaLabel);
    }

    /**
     * @param Collection<int, User> $users
     */
    private function notifyUsers(Collection $users, array $payload, ?string $mailSubject, ?string $mailCtaLabel): void
    {
        $users
            ->unique('id')
            ->each(function (User $user) use ($payload, $mailSubject, $mailCtaLabel): void {
                if ($this->isDuplicate($user, $payload)) {
                    return;
                }

                try {
                    $user->notify(new PlatformNotification($payload, $mailSubject, $mailCtaLabel));
                } catch (Throwable $throwable) {
                    Log::warning('Notification dispatch failed.', [
                        'user_id' => $user->id,
                        'notification_type' => $payload['type'] ?? null,
                        'error' => $throwable->getMessage(),
                    ]);
                }
            });
    }

    private function isDuplicate(User $user, array $payload): bool
    {
        $notification = $user->notifications()
            ->where('type', PlatformNotification::class)
            ->where('data->type', $payload['type'] ?? null)
            ->where('data->entity_type', $payload['entity_type'] ?? null)
            ->where('data->entity_id', $payload['entity_id'] ?? null)
            ->where('data->action_url', $payload['action_url'] ?? null)
            ->latest()
            ->first();

        return (bool) $notification && $notification->created_at?->diffInSeconds(now()) < 60;
    }

    public function buildPayload(
        string $type,
        string $title,
        string $message,
        ?string $entityType = null,
        ?string $entityId = null,
        ?string $actionUrl = null,
        string $priority = 'normal',
        ?string $actorId = null,
        ?string $organisationId = null,
    ): array {
        return [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action_url' => $actionUrl,
            'priority' => $priority,
            'actor_id' => $actorId,
            'organisation_id' => $organisationId,
        ];
    }

    public function notificationPreferenceDefaults(): array
    {
        return [
            'in_app_enabled' => true,
            'email_enabled' => true,
            'type_preferences' => [],
        ];
    }
}
