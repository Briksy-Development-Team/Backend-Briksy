<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionCalendarEvent extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'property_listing_id', 'organization_id', 'title', 'starts_at', 'venue', 'reminders'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'reminders' => 'array'];
    }

    public function propertyListing(): BelongsTo
    {
        return $this->belongsTo(PropertyListing::class);
    }
}
