<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\EmailTemplateIndexRequest;
use App\Http\Requests\Api\SuperAdmin\EmailTemplatePreviewRequest;
use App\Http\Requests\Api\SuperAdmin\EmailTemplateRequest;
use App\Http\Resources\SuperAdmin\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Services\NotificationService;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmailTemplateController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(EmailTemplateIndexRequest $request): JsonResponse
    {
        $query = EmailTemplate::query();
        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());
        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');
        $items = $query->paginate($request->perPage())->withQueryString();
        return $this->paginated(EmailTemplateResource::collection($items)->resolve(), $items, 'Email templates retrieved successfully.');
    }

    public function store(EmailTemplateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $template = EmailTemplate::query()->create([
            'key' => $validated['key'],
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'variables' => $validated['variables'] ?? [],
            'status' => $validated['status'] ?? 'active',
            'created_by' => $request->user()?->id,
        ]);

        $this->notificationService->notifySuperAdmins(
            $this->notificationService->buildPayload(
                'email_template_changed',
                'Email template created',
                sprintf('Email template "%s" was created.', $template->name),
                EmailTemplate::class,
                $template->id,
                '/super-admin/email-templates',
                'normal',
                $request->user()?->id,
                null
            ),
            'Email template created',
            'View template'
        );

        return $this->created(new EmailTemplateResource($template), 'Email template created successfully.');
    }

    public function show(string $emailTemplate): JsonResponse
    {
        return $this->success(new EmailTemplateResource(EmailTemplate::query()->findOrFail($emailTemplate)), 'Email template retrieved successfully.');
    }

    public function update(EmailTemplateRequest $request, string $emailTemplate): JsonResponse
    {
        $template = EmailTemplate::query()->findOrFail($emailTemplate);
        $template->fill($request->validated());
        $template->save();

        $this->notificationService->notifySuperAdmins(
            $this->notificationService->buildPayload(
                'email_template_changed',
                'Email template updated',
                sprintf('Email template "%s" was updated.', $template->name),
                EmailTemplate::class,
                $template->id,
                '/super-admin/email-templates',
                'normal',
                $request->user()?->id,
                null
            ),
            'Email template updated',
            'View template'
        );
        return $this->success(new EmailTemplateResource($template->fresh()), 'Email template updated successfully.');
    }

    public function preview(EmailTemplatePreviewRequest $request, string $emailTemplate): JsonResponse
    {
        $template = EmailTemplate::query()->findOrFail($emailTemplate);
        $variables = $request->input('variables', []);

        $render = function (string $content) use ($variables): string {
            foreach ($variables as $key => $value) {
                $content = str_replace('{{' . $key . '}}', (string) $value, $content);
            }
            return $content;
        };

        return $this->success([
            'id' => $template->id,
            'subject' => $render($template->subject),
            'body' => $render($template->body),
            'variables' => $variables,
        ], 'Email template preview generated successfully.');
    }

    public function activate(string $emailTemplate): JsonResponse
    {
        $template = EmailTemplate::query()->findOrFail($emailTemplate);
        $template->status = 'active';
        $template->save();
        return $this->success(new EmailTemplateResource($template), 'Email template activated successfully.');
    }

    public function deactivate(string $emailTemplate): JsonResponse
    {
        $template = EmailTemplate::query()->findOrFail($emailTemplate);
        $template->status = 'inactive';
        $template->save();
        return $this->success(new EmailTemplateResource($template), 'Email template deactivated successfully.');
    }

    public function destroy(string $emailTemplate): JsonResponse
    {
        EmailTemplate::query()->findOrFail($emailTemplate)->delete();

        $this->notificationService->notifySuperAdmins(
            $this->notificationService->buildPayload(
                'email_template_changed',
                'Email template deleted',
                'An email template was deleted.',
                EmailTemplate::class,
                $emailTemplate,
                '/super-admin/email-templates',
                'normal',
                request()->user()?->id,
                null
            ),
            'Email template deleted',
            'View templates'
        );
        return $this->success([], 'Email template deleted successfully.');
    }
}
