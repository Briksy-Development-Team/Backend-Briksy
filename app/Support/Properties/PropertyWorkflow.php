<?php

namespace App\Support\Properties;

final class PropertyWorkflow
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PENDING_REVIEW = 'Pending Review';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_REJECTED = 'Rejected';
    public const STATUS_PUBLISHED = 'Published';
    public const STATUS_ARCHIVED = 'Archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

    public const MODULE = 'property';

    public const ACTION_CREATED = 'property_created';
    public const ACTION_UPDATED = 'property_updated';
    public const ACTION_SUBMITTED = 'property_submitted_for_review';
    public const ACTION_APPROVED = 'property_approved';
    public const ACTION_REJECTED = 'property_rejected';
    public const ACTION_PUBLISHED = 'property_published';
    public const ACTION_LOCATION_VERIFIED = 'property_location_verified';
    public const ACTION_LOCATION_UNVERIFIED = 'property_location_verification_removed';
    public const ACTION_ARCHIVED = 'property_archived';
    public const ACTION_REPUBLISHED = 'property_republished';
}
