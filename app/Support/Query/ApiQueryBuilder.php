<?php

namespace App\Support\Query;

use Illuminate\Database\Eloquent\Builder;

class ApiQueryBuilder
{
    public static function applySearch(Builder $query, ?string $search, array $columns): void
    {
        if (blank($search) || $columns === []) {
            return;
        }

        $query->where(function (Builder $builder) use ($search, $columns): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $builder->{$method}($column, 'like', "%{$search}%");
            }
        });
    }

    public static function applyExactFilters(Builder $query, array $filters): void
    {
        foreach ($filters as $column => $value) {
            if (!blank($value)) {
                $query->where($column, $value);
            }
        }
    }

    public static function applySort(
        Builder $query,
        ?string $sort,
        string $direction,
        array $allowedSorts,
        string $defaultSort
    ): void {
        $sortColumn = $allowedSorts[$sort] ?? $allowedSorts[$defaultSort] ?? $defaultSort;
        $sortDirection = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortColumn, $sortDirection);
    }

    public static function normalizePerPage(?int $requested, int $default = 15, int $max = 100): int
    {
        if ($requested === null) {
            return $default;
        }

        return max(1, min($requested, $max));
    }
}
