<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Support\Services\ServiceListingRules;
use Illuminate\Foundation\Http\FormRequest;

class ServiceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $service = $this->route('service');

        $rules = ServiceListingRules::store($service?->id);
        $rules['name'][0] = 'sometimes';
        $rules['slug'][0] = 'sometimes';

        return $rules;
    }
}
