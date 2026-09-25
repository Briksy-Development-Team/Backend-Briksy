<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Models\BuilderProject;
use App\Support\Properties\PropertyWorkflow;
use Illuminate\Http\Request;

class BuilderProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = BuilderProject::query()->with('organization')->latest();
        if ($request->filled('filter.status')) {
            $query->where('status', $request->string('filter.status')->toString());
        }

        $projects = $query->paginate($request->integer('items_per_page', 20));
        return $this->paginated($projects, $projects, 'Builder projects retrieved successfully.');
    }

    public function approve(Request $request, BuilderProject $builderProject)
    {
        $builderProject->forceFill([
            'status' => PropertyWorkflow::STATUS_PUBLISHED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
            'published_at' => now(),
        ])->save();

        return $this->success($builderProject, 'Builder project approved and published successfully.');
    }

    public function reject(Request $request, BuilderProject $builderProject)
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:2000']]);
        $builderProject->forceFill([
            'status' => PropertyWorkflow::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
            'published_at' => null,
        ])->save();

        return $this->success($builderProject, 'Builder project rejected successfully.');
    }
}
