<?php

namespace App\Http\Requests\Api\Seeker;

use App\Http\Requests\Api\ApiIndexRequest;
use Illuminate\Validation\Rule;

class FavoriteIndexRequest extends ApiIndexRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'type' => ['nullable', 'string', Rule::in(['property', 'organization'])],
        ]);
    }

    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
        ];
    }
}
