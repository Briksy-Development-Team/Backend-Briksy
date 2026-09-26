<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Admin\AdminBuilderProjectResource;
use App\Models\BuilderProject;
use App\Models\Media;
use App\Services\PublicMediaEntitlementService;
use App\Support\Business\PlanCapabilityResolver;
use App\Support\Properties\PropertyWorkflow;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class BuilderProjectController extends Controller
{
    public function __construct(
        private readonly PlanCapabilityResolver $planCapabilities,
        private readonly PublicMediaEntitlementService $mediaEntitlements,
    ) {}
    public function index(Request $request)
    {
        $items = BuilderProject::where('organization_id', $request->user()->organization_id)->latest()->paginate($request->integer('items_per_page', 20));
        return $this->paginated($items, $items, 'Builder projects retrieved successfully.');
    }

    public function show(Request $request, BuilderProject $builderProject)
    {
        abort_unless($builderProject->organization_id === $request->user()->organization_id, 404);
        $builderProject->load(['organization', 'creator', 'reviewer', 'media']);

        return $this->success(new AdminBuilderProjectResource($builderProject), 'Builder project retrieved successfully.');
    }

    public function store(Request $request)
    {
        $entitlements = $this->planCapabilities->resolved($request->user());
        $limit = $entitlements['limits']['projects'] ?? 0;
        $used = BuilderProject::where('organization_id', $request->user()->organization_id)->count();
        if (!$entitlements['active']) {
            throw new HttpResponseException(response()->json(['success' => false, 'code' => 'SUBSCRIPTION_REQUIRED', 'message' => 'An active subscription is required.'], 402));
        }
        if ((int) $limit > 0 && $used >= (int) $limit) {
            throw new HttpResponseException(response()->json(['success' => false, 'code' => 'PLAN_PROJECT_LIMIT_REACHED', 'message' => sprintf('Your plan allows up to %d projects.', $limit)], 422));
        }
        $data = $this->validatedProjectData($request, true);
        $this->assertMediaEntitlements($request);
        $data['organization_id'] = $request->user()->organization_id;
        $data['created_by'] = $request->user()->id;
        $data['status'] = PropertyWorkflow::STATUS_PENDING_REVIEW;
        $data['submitted_at'] = now();
        $data['reviewed_by'] = null;
        $data['reviewed_at'] = null;
        $data['rejection_reason'] = null;
        $data['published_at'] = null;
        $project = BuilderProject::create($data);
        $this->storeProjectMedia($project, $request);

        return $this->created(new AdminBuilderProjectResource($project->load('media')), 'Builder project created successfully.');
    }

    public function update(Request $request, BuilderProject $builderProject)
    {
        abort_unless($builderProject->organization_id === $request->user()->organization_id, 404);

        $data = $this->validatedProjectData($request);
        $this->assertMediaEntitlements($request, $builderProject);

        $data['status'] = PropertyWorkflow::STATUS_PENDING_REVIEW;
        $data['submitted_at'] = now();
        $data['reviewed_by'] = null;
        $data['reviewed_at'] = null;
        $data['rejection_reason'] = null;
        $data['published_at'] = null;
        $builderProject->update($data);
        $this->storeProjectMedia($builderProject, $request);

        return $this->success(new AdminBuilderProjectResource($builderProject->fresh()->load('media')), 'Builder project updated successfully.');
    }

    public function destroy(Request $request, BuilderProject $builderProject)
    {
        abort_unless($builderProject->organization_id === $request->user()->organization_id, 404);
        $builderProject->delete();

        return $this->success([], 'Builder project deleted successfully.');
    }

    private function validatedProjectData(Request $request, bool $includeStatus = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'project_type' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:20000'],
            'features' => ['nullable', 'array', 'max:30'],
            'features.*' => ['required', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:150'],
            'state' => ['nullable', 'string', 'max:10'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'images' => ['nullable', 'array', 'max:50'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:102400'],
            'videos' => ['nullable', 'array', 'max:20'],
            'videos.*' => ['file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm', 'max:1048576'],
        ];
        if ($includeStatus) {
            $rules['status'] = ['nullable', 'string', 'max:30'];
        }

        return $request->validate($rules);
    }

    private function assertMediaEntitlements(Request $request, ?BuilderProject $project = null): void
    {
        $user = $request->user();
        if (!$user || $user->isSuperAdmin() || $user->isGlobalStaff()) {
            return;
        }

        $images = count((array) $request->file('images', []));
        $videos = count((array) $request->file('videos', []));
        $existingImages = $project?->media()->where('media_type', 'image')->count() ?? 0;
        $existingVideos = $project?->media()->where('media_type', 'video')->count() ?? 0;
        $this->mediaEntitlements->assertUploadAllowed($user->organization, 'project', 'image', $existingImages, $images);
        $this->mediaEntitlements->assertUploadAllowed($user->organization, 'project', 'video', $existingVideos, $videos);
    }

    private function storeProjectMedia(BuilderProject $project, Request $request): void
    {
        $mediaOrder = (int) ($project->media()->max('sort_order') ?? 0);
        foreach ((array) $request->file('images', []) as $index => $file) {
            $path = $file->storePublicly("builder-projects/{$project->id}/images", 'public');
            Media::query()->create([
                'property_listing_id' => null,
                'builder_project_id' => $project->id,
                'file_url' => 'storage/' . ltrim($path, '/'),
                'media_type' => 'image',
                'is_primary' => $index === 0 && $mediaOrder === 0,
                'sort_order' => ++$mediaOrder,
            ]);
        }
        foreach ((array) $request->file('videos', []) as $file) {
            $path = $file->storePublicly("builder-projects/{$project->id}/videos", 'public');
            Media::query()->create([
                'property_listing_id' => null,
                'builder_project_id' => $project->id,
                'file_url' => 'storage/' . ltrim($path, '/'),
                'media_type' => 'video',
                'is_primary' => false,
                'sort_order' => ++$mediaOrder,
            ]);
        }
    }
}
