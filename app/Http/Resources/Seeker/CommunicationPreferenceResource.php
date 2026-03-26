<?php

namespace App\Http\Resources\Seeker;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'sms_enabled' => (bool) $this->sms_enabled,
            'email_enabled' => (bool) $this->email_enabled,
            'push_enabled' => (bool) $this->push_enabled,
            'marketing_opt_in' => (bool) $this->marketing_opt_in,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
