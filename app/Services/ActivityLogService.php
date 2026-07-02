<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ActivityLogService
{
    /**
     * Keys that should never be persisted in audit payloads.
     */
    private const SENSITIVE_KEY_PATTERNS = [
        'password',
        'password_hash',
        'token',
        'access_token',
        'refresh_token',
        'auth_token',
        'otp',
        'otp_code',
        'secret',
        'api_key',
        'api_secret',
        'card',
        'card_number',
        'card_exp',
        'cvv',
        'cvc',
        'pin',
        'remember_token',
    ];

    public function log(
        string $action,
        ?string $module,
        ?string $description,
        array $metadata = [],
        mixed $oldValues = null,
        mixed $newValues = null
    ): ActivityLog {
        $request = $this->request();
        $user = $request?->user();
        $context = $this->requestContext($request);
        $organizationId = $metadata['organization_id'] ?? $this->resolveOrganizationId($user, $metadata, $oldValues, $newValues);
        $metadata = $this->sanitizePayload(Arr::except($metadata, ['organization_id']));

        return ActivityLog::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $user?->id,
            'user_name' => $this->resolveUserName($user, $metadata),
            'user_email' => $this->resolveUserEmail($user, $metadata),
            'user_role' => $this->resolveUserRole($user),
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'method' => $context['method'],
            'route' => $context['route'],
            'ip_address' => $context['ip_address'],
            'user_agent' => $context['user_agent'],
            'old_values' => $this->sanitizePayload($oldValues),
            'new_values' => $this->sanitizePayload($newValues),
            'metadata' => $metadata,
        ]);
    }

    public function logLogin(User $user, array $metadata = []): ActivityLog
    {
        return $this->log(
            'login',
            'auth',
            sprintf('%s logged in.', $this->resolveUserName($user)),
            array_merge($metadata, ['organization_id' => $user->organization_id]),
            null,
            [
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]
        );
    }

    public function logLogout(User $user, array $metadata = []): ActivityLog
    {
        return $this->log(
            'logout',
            'auth',
            sprintf('%s logged out.', $this->resolveUserName($user)),
            array_merge($metadata, ['organization_id' => $user->organization_id])
        );
    }

    public function logFailedLogin(string $email, array $metadata = []): ActivityLog
    {
        return $this->log(
            'failed_login',
            'auth',
            sprintf('Failed login attempt for %s.', $email),
            array_merge($metadata, [
                'attempted_email' => $email,
            ]),
            null,
            [
                'email' => $email,
            ]
        );
    }

    public function logCreate(string $module, Model $model, array $metadata = []): ActivityLog
    {
        return $this->log(
            'created',
            $module,
            sprintf('%s created.', $this->humanizeModule($module)),
            array_merge($this->modelMetadata($model), $metadata),
            null,
            $this->modelValues($model)
        );
    }

    public function logUpdate(string $module, Model $model, mixed $oldValues, mixed $newValues, array $metadata = []): ?ActivityLog
    {
        $oldValues = $this->sanitizePayload($oldValues);
        $newValues = $this->sanitizePayload($newValues);

        if ($oldValues === [] && $newValues === []) {
            return null;
        }

        return $this->log(
            'updated',
            $module,
            sprintf('%s updated.', $this->humanizeModule($module)),
            array_merge($this->modelMetadata($model), $metadata),
            $oldValues,
            $newValues
        );
    }

    public function logDelete(string $module, Model $model, array $metadata = []): ActivityLog
    {
        return $this->log(
            'deleted',
            $module,
            sprintf('%s deleted.', $this->humanizeModule($module)),
            array_merge($this->modelMetadata($model), $metadata),
            $this->modelValues($model),
            null
        );
    }

    private function request(): ?Request
    {
        try {
            $request = request();

            return $request instanceof Request ? $request : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function requestContext(?Request $request): array
    {
        if (!$request) {
            return [
                'method' => null,
                'route' => null,
                'ip_address' => null,
                'user_agent' => null,
            ];
        }

        return [
            'method' => $request->method(),
            'route' => '/' . ltrim($request->path(), '/'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }

    private function resolveOrganizationId(?User $user, array $metadata, mixed $oldValues, mixed $newValues): ?string
    {
        if ($user?->organization_id) {
            return $user->organization_id;
        }

        foreach ([$metadata, $oldValues, $newValues] as $payload) {
            $organizationId = $this->extractOrganizationId($payload);

            if ($organizationId) {
                return $organizationId;
            }
        }

        return null;
    }

    private function extractOrganizationId(mixed $payload): ?string
    {
        if ($payload instanceof Model) {
            return $this->extractOrganizationId($payload->getAttributes());
        }

        if (!is_array($payload)) {
            return null;
        }

        foreach (['organization_id', 'company_id', 'org_id'] as $key) {
            if (!empty($payload[$key])) {
                return (string) $payload[$key];
            }
        }

        return null;
    }

    private function resolveUserName(?User $user, array $metadata = []): ?string
    {
        if ($user) {
            return $user->name ?: $user->display_name ?: $user->email;
        }

        return isset($metadata['user_name']) ? (string) $metadata['user_name'] : null;
    }

    private function resolveUserEmail(?User $user, array $metadata = []): ?string
    {
        if ($user) {
            return $user->email;
        }

        return isset($metadata['user_email']) ? (string) $metadata['user_email'] : ($metadata['attempted_email'] ?? null);
    }

    private function resolveUserRole(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        $user->loadMissing('roles');

        return $user->roles->first()?->name;
    }

    private function modelMetadata(Model $model): array
    {
        $organizationId = $this->extractOrganizationId($model);

        if ($model instanceof Organization) {
            $organizationId = $model->getKey();
        }

        return [
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'organization_id' => $organizationId,
        ];
    }

    private function modelValues(Model $model): array
    {
        return $this->sanitizePayload($model->getAttributes());
    }

    private function humanizeModule(?string $module): string
    {
        if ($module === null || $module === '') {
            return 'Record';
        }

        return Str::headline(str_replace(['_', '-'], ' ', $module));
    }

    private function sanitizePayload(mixed $payload): mixed
    {
        if ($payload === null) {
            return null;
        }

        if ($payload instanceof Model) {
            return $this->sanitizePayload($payload->getAttributes());
        }

        if (is_object($payload)) {
            $payload = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        }

        if (!is_array($payload)) {
            return $payload;
        }

        $sanitized = [];

        foreach ($payload as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                continue;
            }

            $sanitized[$key] = is_array($value) ? $this->sanitizePayload($value) : $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $needle = strtolower($key);

        foreach (self::SENSITIVE_KEY_PATTERNS as $pattern) {
            if (str_contains($needle, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
