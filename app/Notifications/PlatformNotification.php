<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class PlatformNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly array $payload,
        public readonly ?string $mailSubject = null,
        public readonly ?string $mailCtaLabel = null,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $preference = $notifiable->notificationPreference;
        $type = $this->payload['type'] ?? null;

        if ($preference && is_array($preference->type_preferences) && array_key_exists((string) $type, $preference->type_preferences) && !$preference->type_preferences[(string) $type]) {
            return [];
        }

        $channels = [];

        if (!$preference || $preference->in_app_enabled) {
            $channels[] = 'database';
        }

        if (!$preference || $preference->email_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->payload['title'] ?? 'Notification';
        $message = $this->payload['message'] ?? '';
        $actionUrl = $this->absoluteActionUrl($this->payload['action_url'] ?? null);

        $mail = (new MailMessage())
            ->subject($this->mailSubject ?? $title)
            ->greeting($title)
            ->line($message)
            ->line('Received at ' . now()->timezone(config('app.timezone'))->format('j M Y, g:i A'));

        if ($actionUrl) {
            $mail->action($this->mailCtaLabel ?? 'View details', $actionUrl);
        }

        return $mail->salutation('Regards, ' . config('app.name'));
    }

    private function absoluteActionUrl(?string $actionUrl): ?string
    {
        if (!$actionUrl) {
            return null;
        }

        if (Str::startsWith($actionUrl, ['http://', 'https://'])) {
            return $actionUrl;
        }

        $base = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/');

        return $base . '/' . ltrim($actionUrl, '/');
    }
}
