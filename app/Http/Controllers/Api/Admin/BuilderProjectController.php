<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Models\BuilderProject;
use Illuminate\Http\Request;

class BuilderProjectController extends Controller
{
    public function index(Request $request)
    {
        $items = BuilderProject::where('organization_id', $request->user()->organization_id)->latest()->paginate($request->integer('items_per_page', 20));
        return $this->paginated($items, $items, 'Builder projects retrieved successfully.');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required','string','max:150'], 'project_type' => ['nullable','string','max:80'], 'status' => ['nullable','string','max:30'], 'description' => ['nullable','string'], 'location' => ['nullable','string','max:150'], 'state' => ['nullable','string','max:10'], 'postcode' => ['nullable','string','max:10'], 'latitude' => ['nullable','numeric'], 'longitude' => ['nullable','numeric']]);
        $data['organization_id'] = $request->user()->organization_id; $data['created_by'] = $request->user()->id;
        return $this->created(BuilderProject::create($data), 'Builder project created successfully.');
    }
}
