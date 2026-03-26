<?php

namespace App\Http\Requests\Api;

use App\Support\Query\ApiQueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ApiIndexRequest extends FormRequest
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

    abstract public function allowedSorts(): array;

    protected function filterRules(): array
    {
        return [];
    }
}
