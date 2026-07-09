<?php

$registry = [
    [
        'key' => 'auth.login',
        'display_name' => 'Login',
        'category' => 'Authentication',
        'description' => 'Triggered when a user successfully signs in.',
        'payload_schema' => '#/components/schemas/auth.login',
    ],
    [
        'key' => 'auth.logout',
        'display_name' => 'Logout',
        'category' => 'Authentication',
        'description' => 'Triggered when a user signs out.',
        'payload_schema' => '#/components/schemas/auth.logout',
    ],
    [
        'key' => 'user.created',
        'display_name' => 'User Created',
        'category' => 'Users',
        'description' => 'Triggered when a user account is created.',
        'payload_schema' => '#/components/schemas/user.created',
    ],
    [
        'key' => 'user.updated',
        'display_name' => 'User Updated',
        'category' => 'Users',
        'description' => 'Triggered when a user profile is updated.',
        'payload_schema' => '#/components/schemas/user.updated',
    ],
    [
        'key' => 'user.deleted',
        'display_name' => 'User Deleted',
        'category' => 'Users',
        'description' => 'Triggered when a user account is deleted.',
        'payload_schema' => '#/components/schemas/user.deleted',
    ],
    [
        'key' => 'company.created',
        'display_name' => 'Company Created',
        'category' => 'Organizations',
        'description' => 'Triggered when a company is created.',
        'payload_schema' => '#/components/schemas/company.created',
    ],
    [
        'key' => 'company.updated',
        'display_name' => 'Company Updated',
        'category' => 'Organizations',
        'description' => 'Triggered when a company profile changes.',
        'payload_schema' => '#/components/schemas/company.updated',
    ],
    [
        'key' => 'compliance.created',
        'display_name' => 'Compliance Created',
        'category' => 'Compliance',
        'description' => 'Triggered when a compliance record is created.',
        'payload_schema' => '#/components/schemas/compliance.created',
    ],
    [
        'key' => 'compliance.updated',
        'display_name' => 'Compliance Updated',
        'category' => 'Compliance',
        'description' => 'Triggered when a compliance record is updated.',
        'payload_schema' => '#/components/schemas/compliance.updated',
    ],
    [
        'key' => 'compliance.approved',
        'display_name' => 'Compliance Approved',
        'category' => 'Compliance',
        'description' => 'Triggered when a compliance record is approved.',
        'payload_schema' => '#/components/schemas/compliance.approved',
    ],
    [
        'key' => 'compliance.rejected',
        'display_name' => 'Compliance Rejected',
        'category' => 'Compliance',
        'description' => 'Triggered when a compliance record is rejected.',
        'payload_schema' => '#/components/schemas/compliance.rejected',
    ],
    [
        'key' => 'document.uploaded',
        'display_name' => 'Document Uploaded',
        'category' => 'Documents',
        'description' => 'Triggered when a document is uploaded.',
        'payload_schema' => '#/components/schemas/document.uploaded',
    ],
    [
        'key' => 'document.updated',
        'display_name' => 'Document Updated',
        'category' => 'Documents',
        'description' => 'Triggered when a document is updated.',
        'payload_schema' => '#/components/schemas/document.updated',
    ],
    [
        'key' => 'document.deleted',
        'display_name' => 'Document Deleted',
        'category' => 'Documents',
        'description' => 'Triggered when a document is removed.',
        'payload_schema' => '#/components/schemas/document.deleted',
    ],
    [
        'key' => 'subscription.created',
        'display_name' => 'Subscription Created',
        'category' => 'Billing',
        'description' => 'Triggered when a subscription is created.',
        'payload_schema' => '#/components/schemas/subscription.created',
    ],
    [
        'key' => 'subscription.updated',
        'display_name' => 'Subscription Updated',
        'category' => 'Billing',
        'description' => 'Triggered when a subscription is updated.',
        'payload_schema' => '#/components/schemas/subscription.updated',
    ],
    [
        'key' => 'subscription.cancelled',
        'display_name' => 'Subscription Cancelled',
        'category' => 'Billing',
        'description' => 'Triggered when a subscription is cancelled.',
        'payload_schema' => '#/components/schemas/subscription.cancelled',
    ],
    [
        'key' => 'invoice.paid',
        'display_name' => 'Invoice Paid',
        'category' => 'Billing',
        'description' => 'Triggered when an invoice payment succeeds.',
        'payload_schema' => '#/components/schemas/invoice.paid',
    ],
    [
        'key' => 'invoice.failed',
        'display_name' => 'Invoice Failed',
        'category' => 'Billing',
        'description' => 'Triggered when an invoice payment fails.',
        'payload_schema' => '#/components/schemas/invoice.failed',
    ],
    [
        'key' => 'notification.sent',
        'display_name' => 'Notification Sent',
        'category' => 'Notifications',
        'description' => 'Triggered when a notification is sent.',
        'payload_schema' => '#/components/schemas/notification.sent',
    ],
];

return [
    'version' => env('WEBHOOK_PAYLOAD_VERSION', '1.0'),
    'timeout' => env('WEBHOOK_TIMEOUT_SECONDS', 10),
    'max_retry_count' => env('WEBHOOK_MAX_RETRY_COUNT', 5),
    'dispatch_rate_limit_per_minute' => env('WEBHOOK_DISPATCH_RATE_LIMIT_PER_MINUTE', 60),
    'dispatch_rate_limit_decay_seconds' => env('WEBHOOK_DISPATCH_RATE_LIMIT_DECAY_SECONDS', 60),

    'registry' => $registry,

    'events' => collect($registry)->mapWithKeys(static fn (array $event): array => [
        $event['key'] => $event['display_name'],
    ])->all(),

    'categories' => collect($registry)
        ->groupBy('category')
        ->map(static fn ($events, string $category): array => [
            'category' => $category,
            'events' => $events->values()->all(),
        ])
        ->values()
        ->all(),
];
