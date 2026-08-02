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
}
