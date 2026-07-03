<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EmailTemplateService
{
    public function render(string $content, array $variables = []): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', function (array $matches) use ($variables): string {
            $key = $matches[1];
            $value = data_get($variables, $key, '');

            if (is_array($value) || is_object($value)) {
                return '';
            }

            return (string) $value;
        }, $content);
    }

    public function renderTemplate(EmailTemplate $template, array $variables = []): array
    {
        return [
            'subject' => $this->render($template->subject, $variables),
            'body' => $this->render($template->body, $variables),
        ];
    }

    public function sendTestEmail(EmailTemplate $template, string $recipientEmail, array $variables = [], ?Request $request = null): void
    {
        $rendered = $this->renderTemplate($template, $variables);

        Mail::html($rendered['body'], function (Message $message) use ($rendered, $recipientEmail): void {
            $message->to($recipientEmail);
            $message->subject($rendered['subject']);
            $message->from(config('mail.from.address'), config('mail.from.name'));
        });

        $this->logSend($template, $recipientEmail, $rendered, $request);
    }

    public function normalizePayload(array $validated, ?EmailTemplate $existing = null): array
    {
        $slug = $validated['slug'] ?? $validated['key'] ?? $existing?->slug ?? $existing?->key;
        $key = $validated['key'] ?? $validated['slug'] ?? $existing?->key ?? $existing?->slug;
        $isActive = array_key_exists('is_active', $validated)
            ? (bool) $validated['is_active']
            : (($validated['status'] ?? $existing?->status ?? 'active') === 'active');

        return [
            'key' => $key ? Str::slug((string) $key) : null,
            'slug' => $slug ? Str::slug((string) $slug) : null,
            'name' => $validated['name'] ?? $existing?->name,
            'subject' => $validated['subject'] ?? $existing?->subject,
            'body' => $validated['body'] ?? $existing?->body,
            'variables' => $validated['variables'] ?? $existing?->variables ?? [],
            'status' => $isActive ? 'active' : 'inactive',
            'is_active' => $isActive,
            'module' => $validated['module'] ?? $existing?->module,
            'event_key' => $validated['event_key'] ?? $existing?->event_key,
        ];
    }

    private function logSend(EmailTemplate $template, string $recipientEmail, array $rendered, ?Request $request = null): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        try {
            ActivityLog::query()->create([
                'organization_id' => null,
                'user_id' => $request?->user()?->id,
                'user_name' => $request?->user()?->name,
                'user_email' => $request?->user()?->email,
                'user_role' => $request?->user()?->isSuperAdmin() ? 'super_admin' : null,
                'action' => 'send_test',
                'module' => 'email_templates',
                'description' => sprintf('Test email sent for template "%s" to %s.', $template->name, $recipientEmail),
                'method' => 'POST',
                'route' => '/api/super-admin/email-templates/' . $template->id . '/send-test',
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'metadata' => [
                    'template_id' => $template->id,
                    'template_slug' => $template->slug ?? $template->key,
                    'recipient_email' => $recipientEmail,
                    'subject' => $rendered['subject'],
                ],
            ]);
        } catch (\Throwable) {
            // Logging must never block email delivery.
        }
    }
}
