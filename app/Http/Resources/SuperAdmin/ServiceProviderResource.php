<?php

namespace App\Http\Resources\SuperAdmin;

use App\Models\Organization;
use App\Models\SoleTraderProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof Organization) {
            return [
                'id' => $this->resource->id,
                'provider_type' => 'organization',
                'name' => $this->resource->name,
                'slug' => $this->resource->slug,
                'email' => $this->resource->contact_email,
                'phone' => $this->resource->contact_phone,
                'is_verified' => (bool) $this->resource->is_verified,
                'organization_type' => $this->resource->relationLoaded('organizationType') ? [
                    'id' => $this->resource->organizationType?->id,
                    'name' => $this->resource->organizationType?->name,
                    'slug' => $this->resource->organizationType?->slug,
                ] : null,
                'created_at' => $this->resource->created_at?->toISOString(),
            ];
        }

        if ($this->resource instanceof SoleTraderProfile) {
            return [
                'id' => $this->resource->id,
                'provider_type' => 'sole_trader',
                'name' => $this->resource->trading_name ?: $this->resource->user?->display_name ?: $this->resource->user?->name,
                'slug' => $this->resource->organization?->slug,
                'email' => $this->resource->user?->email,
                'phone' => $this->resource->user?->mobile_number,
                'is_verified' => (bool) $this->resource->user?->id_verified,
                'organization_type' => $this->resource->organization?->relationLoaded('organizationType') ? [
                    'id' => $this->resource->organization?->organizationType?->id,
                    'name' => $this->resource->organization?->organizationType?->name,
                    'slug' => $this->resource->organization?->organizationType?->slug,
                ] : null,
                'sole_trader' => [
                    'user_id' => $this->resource->user_id,
                    'organization_id' => $this->resource->organization_id,
                    'trading_name' => $this->resource->trading_name,
                    'abn' => $this->resource->abn,
                    'trade_license_number' => $this->resource->trade_license_number,
                    'primary_service_postcode' => $this->resource->primary_service_postcode,
                    'service_radius_km' => $this->resource->service_radius_km,
                ],
                'created_at' => $this->resource->created_at?->toISOString(),
            ];
        }

        $record = (array) $this->resource;

        return [
            'id' => $record['id'] ?? null,
            'provider_type' => $record['provider_type'] ?? null,
            'name' => $record['name'] ?? null,
            'slug' => $record['slug'] ?? null,
            'email' => $record['email'] ?? null,
            'phone' => $record['phone'] ?? null,
            'is_verified' => (bool) ($record['is_verified'] ?? false),
            'organization_type' => [
                'id' => $record['type_id'] ?? null,
                'name' => $record['type_name'] ?? null,
                'slug' => $record['type_slug'] ?? null,
            ],
            'sole_trader' => ($record['provider_type'] ?? null) === 'sole_trader' ? [
                'user_id' => $record['user_id'] ?? null,
                'organization_id' => $record['organization_id'] ?? null,
                'trading_name' => $record['trading_name'] ?? null,
                'primary_service_postcode' => $record['primary_service_postcode'] ?? null,
            ] : null,
            'created_at' => $record['created_at'] ?? null,
        ];
    }
}
