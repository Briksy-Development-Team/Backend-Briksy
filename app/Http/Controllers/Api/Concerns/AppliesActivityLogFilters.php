<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Support\Query\ApiQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesActivityLogFilters
{
    protected function applyActivityLogFilters(Builder $query, Request $request, bool $allowOrganizationFilter = false): void
    {
        ApiQueryBuilder::applySearch($query, $request->search(), [
            'user_name',
            'user_email',
            'action',
            'module',
            'description',
        ]);

        $filters = $request->input('filter', []);

        if ($allowOrganizationFilter) {
            $organizationId = $filters['organization_id'] ?? $filters['company_id'] ?? null;
            if ($organizationId) {
                $query->where('organization_id', $organizationId);
            }
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['user'])) {
            $userSearch = (string) $filters['user'];
            $query->where(function (Builder $builder) use ($userSearch): void {
                $builder->where('user_name', 'like', "%{$userSearch}%")
                    ->orWhere('user_email', 'like', "%{$userSearch}%")
                    ->orWhere('user_id', 'like', "%{$userSearch}%");
            });
        }

        if (!empty($filters['role'])) {
            $query->where('user_role', 'like', '%' . $filters['role'] . '%');
        }

        foreach (['action', 'module', 'ip_address'] as $key) {
            if (!empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!$request->filled('sort')) {
            $query->orderByDesc('created_at');
            return;
        }

        ApiQueryBuilder::applySort(
            $query,
            $request->sort(),
            $request->direction(),
            $request->allowedSorts(),
            'created_at'
        );
    }
}
