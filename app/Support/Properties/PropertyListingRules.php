<?php

namespace App\Support\Properties;

use Illuminate\Validation\Rule;
use App\Models\PropertyType;
use App\Support\Properties\CommercialTransactionStatus;

final class PropertyListingRules
{
    public static function store(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            // Nullable keeps legacy/imported residential rows compatible;
            // the Add Property UI requires an explicit category/type.
            'property_type_id' => ['nullable', 'uuid', 'exists:property_types,id'],
            'address_line_1' => ['nullable', 'string', 'max:500'],
            'address_line_2' => ['nullable', 'string', 'max:500'],
            'address' => ['nullable', 'string', 'max:500'],
            'full_address' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(PropertyWorkflow::STATUSES)],
            'listing_purpose' => ['sometimes', Rule::in(['SELL', 'RENT', 'BOTH'])],
            'transaction_status' => ['nullable', Rule::in(CommercialTransactionStatus::VALUES)],
            'price' => ['nullable', 'numeric', 'min:0'],
            'suburb' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:50'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric'],
            'formatted_address' => ['nullable', 'string'],
            'place_id' => ['nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'numeric'],
            'features' => ['nullable', 'array'],
            'features.*' => ['uuid', 'distinct', 'exists:property_features,id'],
            'location_verified' => ['prohibited'],
            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'image', 'max:5120'],
            'videos' => ['nullable', 'array'],
            'videos.*' => ['file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm', 'max:1048576'],
        ];
    }

    public static function update(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'property_type_id' => ['sometimes', 'nullable', 'uuid', 'exists:property_types,id'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:500'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:500'],
            'address' => ['nullable', 'string', 'max:500'],
            'full_address' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(PropertyWorkflow::STATUSES)],
            'listing_purpose' => ['sometimes', Rule::in(['SELL', 'RENT', 'BOTH'])],
            'transaction_status' => ['nullable', Rule::in(CommercialTransactionStatus::VALUES)],
            'price' => ['nullable', 'numeric', 'min:0'],
            'suburb' => ['nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:50'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric'],
            'formatted_address' => ['sometimes', 'nullable', 'string'],
            'place_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'numeric'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['uuid', 'distinct', 'exists:property_features,id'],
            'location_verified' => ['prohibited'],
            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'image', 'max:5120'],
            'videos' => ['nullable', 'array'],
            'videos.*' => ['file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm', 'max:1048576'],
        ];
    }

    public static function categoryErrors(array $data): array
    {
        $category = ! empty($data['property_type_id'])
            ? PropertyType::query()->whereKey($data['property_type_id'])->value('category')
            : null;
        $transactionStatus = $data['transaction_status'] ?? null;

        if ($category === 'commercial' && blank($transactionStatus)) {
            return ['transaction_status' => ['A commercial transaction status is required.']];
        }

        if ($category !== 'commercial' && filled($transactionStatus)) {
            return ['transaction_status' => ['Transaction status is only valid for commercial properties.']];
        }

        return [];
    }
}
