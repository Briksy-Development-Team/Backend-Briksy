<?php

namespace App\Http\Requests\Api\Seeker;

use App\Http\Requests\Api\ApiIndexRequest;
use Illuminate\Validation\Validator;
use App\Support\Properties\CommercialTransactionStatus;

class PropertyListingIndexRequest extends ApiIndexRequest
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('min_price') && $this->filled('max_price') && (float) $this->input('min_price') > (float) $this->input('max_price')) {
                $validator->errors()->add('max_price', 'The maximum price must be greater than or equal to the minimum price.');
            }
        });
    }

    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'title' => 'title',
            'rating' => 'avg_prop_rating',
            'price' => 'price',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'suburb' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'organization_slug' => ['nullable', 'string', 'max:100'],
            'organization_type' => ['nullable', 'string', 'max:100'],
            'service_slug' => ['nullable', 'string', 'max:100'],
            'verified_only' => ['nullable', 'boolean'],
            'purpose' => ['nullable', 'string', 'in:sell,rent,both,SELL,RENT,BOTH'],
            'transaction_status' => ['nullable', 'string', 'in:'.implode(',', CommercialTransactionStatus::VALUES)],
            'category' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'bedrooms' => ['nullable', 'integer', 'min:0'],
            'bathrooms' => ['nullable', 'integer', 'min:0'],
            'car_spaces' => ['nullable', 'integer', 'min:0'],
            'min_land_size' => ['nullable', 'numeric', 'min:0'],
            'max_land_size' => ['nullable', 'numeric', 'gte:min_land_size'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'distinct', 'exists:property_features,slug'],
        ];
    }
}
