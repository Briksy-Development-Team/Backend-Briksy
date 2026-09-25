<?php

namespace App\Http\Requests\Api\Seeker;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $collectionId = $this->route('collection')?->id ?? $this->route('collection');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('collections', 'name')
                    ->ignore($collectionId)
                    ->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
        ];
    }
}
