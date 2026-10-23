<?php

namespace App\Services;

use App\Models\BuilderProject;
use App\Models\MarketingReel;
use App\Models\Organization;
use App\Models\PropertyListing;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MarketingReelService
{
    public const ENTITY_MAP = [
        'organization' => Organization::class,
        'property' => PropertyListing::class,
        'builder-project' => BuilderProject::class,
        'service' => Service::class,
    ];

    public function upload(Model $entity, Organization $organization, array $files, bool $bypassLimit = false): array
    {
        return DB::transaction(function () use ($entity, $organization, $files, $bypassLimit): array {
            $organization->newQuery()->whereKey($organization->getKey())->lockForUpdate()->first();
            if (!$bypassLimit) {
                $limit = $this->limit($organization);
                $existing = MarketingReel::query()->where('model_type', $entity::class)->where('model_id', $entity->getKey())->lockForUpdate()->count();
                if ($entity instanceof Organization) $existing += count($organization->reel_urls ?? []);
                if ($limit !== null && $existing + count($files) > $limit) {
                    $this->error('PLAN_REELS_LIMIT_REACHED', sprintf('Your current plan allows up to %d marketing shorts/reels.', $limit));
                }
            }
            $order = (int) MarketingReel::query()->where('model_type', $entity::class)->where('model_id', $entity->getKey())->max('sort_order');
            $created = [];
            $paths = [];
            try {
                foreach ($files as $file) {
                    if (!$file instanceof UploadedFile) continue;
                    $path = $file->storePublicly("marketing-reels/{$organization->id}/{$entity->getKey()}", 'public');
                    $paths[] = $path;
                    $created[] = MarketingReel::query()->create([
                        'organization_id' => $organization->id,
                        'model_type' => $entity::class,
                        'model_id' => $entity->getKey(),
                        'file_url' => 'storage/' . ltrim($path, '/'),
                        'sort_order' => ++$order,
                    ]);
                }
            } catch (\Throwable $exception) {
                foreach ($paths as $path) Storage::disk('public')->delete($path);
                throw $exception;
            }
            return $created;
        });
    }

    public function delete(MarketingReel $reel): void
    {
        $path = preg_replace('#^storage/#', '', ltrim((string) $reel->file_url, '/')) ?? '';
        if ($path !== '' && Storage::disk('public')->exists($path)) Storage::disk('public')->delete($path);
        $reel->delete();
    }

    public function limit(Organization $organization): ?int
    {
        $subscription = $organization->currentSubscription()->with('plan')->first();
        $feature = collect($subscription?->plan?->features ?? [])->first(
            fn (array $item): bool => in_array(strtolower((string) ($item['name'] ?? '')), ['reels', 'marketing content', 'marketing content (shorts/reels)'], true)
        );
        if (!$subscription || !$feature || !($feature['enabled'] ?? false)) return 0;
        return array_key_exists('value', $feature) && $feature['value'] !== null ? max(0, (int) $feature['value']) : null;
    }

    private function error(string $code, string $message): never
    {
        throw new HttpResponseException(response()->json(['success' => false, 'code' => $code, 'message' => $message], 422));
    }
}
