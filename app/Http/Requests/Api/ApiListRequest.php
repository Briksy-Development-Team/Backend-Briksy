<?php

namespace App\Http\Requests\Api;

use App\Support\Query\ApiQueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ApiListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'search' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'string', Rule::in(array_keys($this->allowedSorts()))],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'filter' => ['nullable', 'array'],
        ], $this->filterRules());
    }

    public function search(): ?string
    {
        return $this->string('search')->toString() ?: null;
    }

    public function sort(): ?string
    {
        return $this->string('sort')->toString() ?: null;
    }

    public function direction(): string
    {
        return $this->string('direction')->toString() ?: 'desc';
    }

    public function perPage(): int
    {
        return ApiQueryBuilder::normalizePerPage($this->integer('per_page') ?: null);
    }

    public function filters(): array
    {
        return $this->input('filter', []);
    }

    public function allowedFilters(): array
    {
        return [];
    }

    public function searchableColumns(): array
    {
        return [];
    }

    abstract public function allowedSorts(): array;

    protected function filterRules(): array
    {
        return [];
    }
}