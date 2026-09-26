<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\OrganizationIndexRequest;
use App\Http\Resources\Seeker\OrganizationResource;
use App\Models\Organization;
use App\Models\BuilderProject;
use App\Models\VisitorLog;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class OrganizationSearchController extends Controller
{
    public function index(OrganizationIndexRequest $request)
    {
        $query = Organization::query()
            ->with(['organizationType', 'currentSubscription.plan', 'services.media', 'ownedServices.media', 'serviceGroups']);

        if ($viewerId = $request->user('sanctum')?->id) {
            $query->withExists(['favorites as is_favourite' => fn ($favoriteQuery) => $favoriteQuery->where('user_id', $viewerId)]);
        }

        ApiQueryBuilder::applySearch($query, $request->search(), ['name', 'slug', 'abn', 'address', 'state', 'postcode', 'contact_email']);

        if ($request->filled('type')) {
            $query->whereHas('organizationType', fn ($typeQuery) => $typeQuery->where('slug', $request->string('type')->toString()));
        }

        if ($request->filled('service_slug')) {
            $serviceSlug = $request->string('service_slug')->toString();
            $serviceCategory = collect(config('service_categories', []))->firstWhere('slug', $serviceSlug);
            $categoryValues = collect([
                $serviceSlug,
                $serviceCategory['label'] ?? null,
                // Keep existing records created with the previous typo/value format visible.
                $serviceSlug === 'landscapers' ? 'Landscappers' : null,
                $serviceSlug === 'landscapers' ? 'Landscaping' : null,
            ])->filter()->map(fn ($value): string => strtolower(trim((string) $value)))->unique()->values()->all();
            $query->where(function ($organizationQuery) use ($serviceSlug, $categoryValues): void {
                $matchesService = function ($serviceQuery) use ($serviceSlug, $categoryValues): void {
                    $serviceQuery->where('services.slug', $serviceSlug);
                    if ($categoryValues !== []) {
                        $placeholders = implode(', ', array_fill(0, count($categoryValues), '?'));
                        $serviceQuery->orWhereRaw("LOWER(services.category) IN ({$placeholders})", $categoryValues);
                    }
                };
                $organizationQuery
                    ->whereHas('services', $matchesService)
                    ->orWhereHas('ownedServices', $matchesService);
            });
        }

        if ($request->filled('service_group_slug')) {
            $query->whereHas('serviceGroups', fn ($groupQuery) => $groupQuery->where('service_groups.slug', $request->string('service_group_slug')->toString()));
        }

        if ($request->boolean('verified_only')) {
            $query->where('is_verified', true);
        }

        if ($request->filled('sort')) {
            ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'priority');
        } else {
            $query->orderBy('ranking_priority')->orderByDesc('avg_org_rating');
        }

        $organizations = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            OrganizationResource::collection($organizations),
            $organizations,
            'Organizations retrieved successfully.'
        );
    }

    public function show(Organization $organization)
    {
        $organization->load(['organizationType', 'currentSubscription.plan', 'services.media', 'ownedServices.media', 'serviceGroups']);
        $this->recordVisit($organization, request());

        return $this->success(
            new OrganizationResource($organization),
            'Organization retrieved successfully.'
        );
    }

    public function builderProjects(Request $request, Organization $organization)
    {
        $projects = BuilderProject::query()
            ->where('organization_id', $organization->id)
            ->where('status', \App\Support\Properties\PropertyWorkflow::STATUS_PUBLISHED)
            ->with('media')
            ->latest()
            ->get(['id', 'name', 'project_type', 'status', 'description', 'features', 'location', 'state', 'postcode']);

        return $this->success($projects->map(fn (BuilderProject $project): array => [
            'id' => $project->id,
            'name' => $project->name,
            'project_type' => $project->project_type,
            'status' => $project->status,
            'description' => $project->description,
            'features' => $project->features ?? [],
            'location' => $project->location,
            'state' => $project->state,
            'postcode' => $project->postcode,
            'images' => $project->media
                ->where('media_type', 'image')
                ->map(fn ($media): array => [
                    'id' => $media->id,
                    'url' => $request->getSchemeAndHttpHost() . '/api/media/' . $media->id,
                    'is_primary' => (bool) $media->is_primary,
                ])->values()->all(),
        ])->values()->all(), 'Builder projects retrieved successfully.');
    }

    public function builderProject(BuilderProject $builderProject)
    {
        abort_unless($builderProject->status === \App\Support\Properties\PropertyWorkflow::STATUS_PUBLISHED, 404);

        $builderProject->load(['organization', 'creator', 'media']);

        return $this->success([
            'id' => $builderProject->id,
            'name' => $builderProject->name,
            'project_type' => $builderProject->project_type,
            'status' => $builderProject->status,
            'description' => $builderProject->description,
            'features' => $builderProject->features ?? [],
            'location' => $builderProject->location,
            'state' => $builderProject->state,
            'postcode' => $builderProject->postcode,
            'images' => $builderProject->media
                ->where('media_type', 'image')
                ->map(fn ($media): array => [
                    'id' => $media->id,
                    'url' => request()->getSchemeAndHttpHost() . '/api/media/' . $media->id,
                    'is_primary' => (bool) $media->is_primary,
                ])->values()->all(),
            'videos' => $builderProject->media
                ->where('media_type', 'video')
                ->map(fn ($media): array => [
                    'id' => $media->id,
                    'url' => request()->getSchemeAndHttpHost() . '/api/media/' . $media->id,
                ])->values()->all(),
            'organization' => $builderProject->organization ? [
                'id' => $builderProject->organization->id,
                'name' => $builderProject->organization->name,
                'slug' => $builderProject->organization->slug,
                'logo_url' => $builderProject->organization->logo_url,
            ] : null,
            'creator' => $builderProject->creator ? [
                'id' => $builderProject->creator->id,
                'name' => $builderProject->creator->display_name ?: $builderProject->creator->name,
                'email' => $builderProject->creator->email,
                'mobile_number' => $builderProject->creator->mobile_number,
            ] : null,
        ], 'Builder project retrieved successfully.');
    }

    private function recordVisit(Organization $organization, Request $request): void
    {
        if (!Schema::hasTable('visitor_logs')) {
            return;
        }

        VisitorLog::query()->create([
            'viewer_id' => $request->user()?->id,
            'organization_id' => $organization->id,
            'property_listing_id' => null,
            'ip_address' => $request->ip(),
        ]);
    }
}
