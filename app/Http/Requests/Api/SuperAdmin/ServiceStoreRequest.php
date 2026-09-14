<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Support\Services\ServiceListingRules;
use Illuminate\Foundation\Http\FormRequest;

class ServiceStoreRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('service_area_geometry'))) {
            $decoded = json_decode($this->input('service_area_geometry'), true);
            if (is_array($decoded)) {
                $this->merge(['service_area_geometry' => $decoded]);
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ServiceListingRules::store();
    }
}
