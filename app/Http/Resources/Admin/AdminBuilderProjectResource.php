<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBuilderProjectResource extends JsonResource
{
    private function mediaUrl(Request $request, ?string $url, ?string $mediaId = null): ?string
    {
        if (!$url) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $path = parse_url($url, PHP_URL_PATH);
            if (is_string($path) && str_contains($path, '/storage/')) {
                return $mediaId ? rtrim($request->getSchemeAndHttpHost(), '/') . "/api/media/{$mediaId}" : rtrim($request->getSchemeAndHttpHost(), '/') . $path;
            }
            return $url;
        }

        return $mediaId
            ? rtrim($request->getSchemeAndHttpHost(), '/') . "/api/media/{$mediaId}"
            : rtrim($request->getSchemeAndHttpHost(), '/') . '/' . ltrim($url, '/');
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_id' => $this->id,
            'name' => $this->name,
            'project_type' => $this->project_type,
            'status' => $this->status,
            'description' => $this->description,
            'features' => $this->features ?? [],
            'location' => $this->location,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'published_at' => $this->published_at?->toISOString(),
            'images' => $this->whenLoaded('media', fn (): array => $this->media
                ->where('media_type', 'image')
                ->map(fn ($media): array => [
                    'id' => $media->id,
                    'url' => $this->mediaUrl($request, $media->file_url, (string) $media->id),
                    'is_primary' => (bool) $media->is_primary,
                    'sort_order' => (int) $media->sort_order,
                ])->values()->all()),
            'videos' => $this->whenLoaded('media', fn (): array => $this->media
                ->where('media_type', 'video')
                ->map(fn ($media): array => [
                    'id' => $media->id,
                    'url' => $this->mediaUrl($request, $media->file_url, (string) $media->id),
                    'is_primary' => (bool) $media->is_primary,
                    'sort_order' => (int) $media->sort_order,
                ])->values()->all()),
            'organization' => $this->whenLoaded('organization', fn (): ?array => $this->organization ? [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn (): ?array => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
