<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketingReelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => rtrim($request->getSchemeAndHttpHost(), '/') . '/api/marketing-reels/' . $this->id,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
