<?php

namespace App\Http\Requests\Api\Admin;

use App\Support\Properties\PropertyListingRules;
use Illuminate\Foundation\Http\FormRequest;

class PropertyListingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return PropertyListingRules::update();
    }

    protected function passedValidation(): void
    {
        $data = $this->validated();
        $property = $this->route('propertyListing');
        $typeWasSubmitted = array_key_exists('property_type_id', $data);
        if ($property && ! $typeWasSubmitted) {
            $data['property_type_id'] = $property->property_type_id;
        }
        if ($property && ! array_key_exists('transaction_status', $data) && ! $typeWasSubmitted) {
            $data['transaction_status'] = $property->transaction_status;
        }

        $errors = PropertyListingRules::categoryErrors($data);
        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }
}
