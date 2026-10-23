<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MarketingReelResource;
use App\Models\MarketingReel;
use App\Models\Organization;
use App\Services\MarketingReelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MarketingReelController extends Controller
{
    public function __construct(private readonly MarketingReelService $reels) {}

    public function index(Request $request, string $entityType, string $entityId)
    {
        [$entity, $organization] = $this->resolveEntity($request, $entityType, $entityId);
        $items = MarketingReel::query()->where('model_type', $entity::class)->where('model_id', $entity->getKey())->orderBy('sort_order')->get();
        $used = $items->count() + ($entity instanceof Organization ? count($organization->reel_urls ?? []) : 0);
        $limit = $this->reels->limit($organization);
        return $this->success(['items' => MarketingReelResource::collection($items)->resolve(), 'limit' => $limit, 'used' => $used, 'remaining' => $limit === null ? null : max(0, $limit - $used)], 'Marketing reels retrieved successfully.');
    }

    public function store(Request $request, string $entityType, string $entityId)
    {
        [$entity, $organization] = $this->resolveEntity($request, $entityType, $entityId);
        $validated = $request->validate([
            'reels' => ['required', 'array', 'max:20'],
            'reels.*' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm', 'mimes:mp4,mov,avi,mkv,webm', 'max:1048576'],
        ]);
        $items = $this->reels->upload($entity, $organization, $validated['reels'], $request->user()->isSuperAdmin() || $request->user()->isGlobalStaff());
        return $this->created(['items' => MarketingReelResource::collection($items)->resolve()], 'Marketing reels uploaded successfully.');
    }

    public function show(MarketingReel $marketingReel)
    {
        $model = $marketingReel->model;
        $visible = $model instanceof \App\Models\PropertyListing
            ? in_array($model->status, ['Published', 'Approved'], true)
            : ($model instanceof \App\Models\BuilderProject ? in_array($model->status, ['published', 'approved', 'Published', 'Approved'], true) : true);
        abort_unless($visible, 404);
        $path = preg_replace('#^storage/#', '', ltrim((string) $marketingReel->file_url, '/')) ?? '';
        abort_unless($path !== '' && Storage::disk('public')->exists($path), 404);
        return response()->file(Storage::disk('public')->path($path), ['Cache-Control' => 'public, max-age=86400']);
    }

    public function destroy(Request $request, MarketingReel $marketingReel)
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->isGlobalStaff() || $request->user()->organization_id === $marketingReel->organization_id, 403);
        $this->reels->delete($marketingReel);
        return $this->success([], 'Marketing reel deleted successfully.');
    }

    private function resolveEntity(Request $request, string $entityType, string $entityId): array
    {
        $class = MarketingReelService::ENTITY_MAP[$entityType] ?? null;
        abort_unless($class, 404);
        $entity = $class::query()->findOrFail($entityId);
        $organization = $entity instanceof Organization ? $entity : ($entity->organization ?? null);
        abort_unless($organization, 404);
        abort_unless($request->user()->isSuperAdmin() || $request->user()->isGlobalStaff() || $request->user()->organization_id === $organization->id, 403);
        return [$entity, $organization];
    }
}
