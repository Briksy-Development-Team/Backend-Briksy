<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\EmailTemplateIndexRequest;
use App\Http\Requests\Api\SuperAdmin\EmailTemplatePreviewRequest;
use App\Http\Requests\Api\SuperAdmin\EmailTemplateRequest;
use App\Http\Resources\SuperAdmin\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use App\Services\NotificationService;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly EmailTemplateService $emailTemplateService,
    ) {
    }

    public function index(EmailTemplateIndexRequest $request): JsonResponse
    {
        $query = EmailTemplate::query();
        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());
        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $items = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            EmailTemplateResource::collection($items)->resolve(),
            $items,
            'Email templates retrieved successfully.'
        );
    }

    public function store(EmailTemplateRequest $request): JsonResponse
    {
        $validated = $this->emailTemplateService->normalizePayload($request->validated());
        $template = EmailTemplate::query()->create(array_merge($validated, [
            'created_by' => $request->user()?->id,
        ]));

        $this->notifyChange($request, 'created', $template);

        return $this->created(new EmailTemplateResource($template), 'Email template created successfully.');
    }

    public function show(string $emailTemplate): JsonResponse
    {
        return $this->success(
            new EmailTemplateResource(EmailTemplate::query()->findOrFail($emailTemplate)),
            'Email template retrieved successfully.'
        );
    }

    public function update(EmailTemplateRequest $request, string $emailTemplate): JsonResponse
    {
        $template = EmailTemplate::query()->findOrFail($emailTemplate);
        $template->fill($this->emailTemplateService->normalizePayload($request->validated(), $template));
        $template->save();

        $this->notifyChange($request, 'updated', $template);

        return $this->success(new EmailTemplateResource($template->fresh()), 'Email template updated successfully.');
    }

    public function preview(EmailTemplatePreviewRequest $request, string $emailTemplate): JsonResponse
    {
        $template = EmailTemplate::query()->findOrFail($emailTemplate);
        $variables = $request->input('variables', []);
        $rendered = $this->emailTemplateService->renderTemplate($template, $variables);

        return $this->success([
            'id' => $template->id,
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'variables' => $variables,
        ], 'Email template preview generated successfully.');
    }

    public function sendTest(Request $request, string $emailTemplate): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'variables' => ['nullable', 'array'],
        ]);

        $template = EmailTemplate::query()->findOrFail($emailTemplate);

        try {
            $this->emailTemplateService->sendTestEmail(
                $template,
                $validated['email'],
                $validated['variables'] ?? [],
                $request
            );
        } catch (\Throwable $throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email. Check SMTP credentials and mail settings.',
                'error' => $throwable->getMessage(),
            ], 422);
        }

        return $this->success([
            'email' => $validated['email'],
            'template_id' => $template->id,
        ], 'Test email sent successfully.');
    }

    public function toggle(string $emailTemplate): JsonResponse
    {
        $template = EmailTemplate::query()->findOrFail($emailTemplate);
        $template->is_active = !($template->is_active ?? ($template->status === 'active'));
        $template->status = $template->is_active ? 'active' : 'inactive';
        $template->save();

        return $this->success(new EmailTemplateResource($template), 'Email template status updated successfully.');
    }

    public function activate(string $emailTemplate): JsonResponse
    {
        $template = EmailTemplate::query()->findOrFail($emailTemplate);
        $template->is_active = true;
        $template->status = 'active';
        $template->save();

        return $this->success(new EmailTemplateResource($template), 'Email template activated successfully.');
    }

    public function deactivate(string $emailTemplate): JsonResponse
    {
        $template = EmailTemplate::query()->findOrFail($emailTemplate);
        $template->is_active = false;
        $template->status = 'inactive';
        $template->save();

        return $this->success(new EmailTemplateResource($template), 'Email template deactivated successfully.');
    }

    public function destroy(string $emailTemplate): JsonResponse
    {
        EmailTemplate::query()->findOrFail($emailTemplate)->delete();

        $this->notifyChange(null, 'deleted', null, $emailTemplate);

        return $this->success([], 'Email template deleted successfully.');
    }

    private function notifyChange(?Request $request, string $action, ?EmailTemplate $template, ?string $templateId = null): void
    {
        if (!$template && !$templateId) {
            return;
        }

        $templateId ??= $template?->id;
        $message = match ($action) {
            'created' => sprintf('Email template "%s" was created.', $template?->name),
            'updated' => sprintf('Email template "%s" was updated.', $template?->name),
            'deleted' => 'An email template was deleted.',
            default => 'Email template changed.',
        };

        $this->notificationService->notifySuperAdmins(
            $this->notificationService->buildPayload(
                'email_template_' . $action,
                'Email template ' . $action,
                $message,
                EmailTemplate::class,
                $templateId,
                '/super-admin/email-templates',
                'normal',
                $request?->user()?->id,
                null
            ),
            'Email template ' . $action,
            'View templates'
        );
    }
}
