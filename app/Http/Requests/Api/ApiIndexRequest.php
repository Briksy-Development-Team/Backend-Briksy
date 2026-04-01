<?php

namespace App\Http\Requests\Api;

abstract class ApiIndexRequest extends ApiListRequest
{
    abstract public function allowedSorts(): array;

    protected function filterRules(): array
    {
        return [];
    }
}
