<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Models\AuctionCalendarEvent;
use App\Models\PropertyListing;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuctionCalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = AuctionCalendarEvent::query()->where('user_id', $request->user()->id)->with('propertyListing')->orderBy('starts_at')->get()->map(fn (AuctionCalendarEvent $event): array => $this->serialize($event));
        return $this->success($events, 'Auction calendar retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_listing_id' => ['required', 'uuid', 'exists:property_listings,id'],
            'starts_at' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:500'],
        ]);
        $property = PropertyListing::query()->findOrFail($data['property_listing_id']);
        abort_unless((bool) $property->is_auction, 422, 'This property is not an auction listing.');
        $event = AuctionCalendarEvent::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'property_listing_id' => $property->id],
            ['organization_id' => $property->org_id, 'title' => 'Auction: ' . $property->title, 'starts_at' => Carbon::parse($data['starts_at']), 'venue' => $data['venue'] ?? $property->auction_venue, 'reminders' => ['P-7D', 'P-3D', 'P-1D', 'PT0S']],
        );
        return $this->success($this->serialize($event->load('propertyListing')), 'Auction added to your Briksy calendar.', 201);
    }

    public function destroy(Request $request, AuctionCalendarEvent $event): JsonResponse
    {
        abort_unless($event->user_id === $request->user()->id, 403);
        $event->delete();
        return $this->success([], 'Auction removed from your Briksy calendar.');
    }

    private function serialize(AuctionCalendarEvent $event): array
    {
        return ['id' => $event->id, 'property_listing_id' => $event->property_listing_id, 'title' => $event->title, 'starts_at' => $event->starts_at?->toISOString(), 'venue' => $event->venue, 'reminders' => $event->reminders ?? [], 'property' => $event->propertyListing ? ['title' => $event->propertyListing->title, 'address' => $event->propertyListing->full_address ?: $event->propertyListing->address] : null];
    }
}
