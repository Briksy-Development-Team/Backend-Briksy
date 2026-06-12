<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesOrganizationScope
{
    protected function organizationId(Request $request): ?string
    {
        return $request->user()?->organization_id;
    }

    protected function scopedQuery(Builder $query, Request $request): Builder
    {
        $organizationId = $this->organizationId($request);

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        return $query;
    }

    protected function requireOrganizationId(Request $request): string
    {
        $organizationId = $this->organizationId($request);

        abort_unless($organizationId, 403, 'Admin account is not assigned to an organization.');

        return $organizationId;
    }
}
