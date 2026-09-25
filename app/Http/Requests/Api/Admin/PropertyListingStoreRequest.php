<?php

namespace App\Http\Requests\Api\Admin;

use App\Support\Properties\PropertyListingRules;
use Illuminate\Foundation\Http\FormRequest;

class PropertyListingStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return PropertyListingRules::store();
    }

    protected function passedValidation(): void
    {
        $errors = PropertyListingRules::categoryErrors($this->validated());
        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }
}
