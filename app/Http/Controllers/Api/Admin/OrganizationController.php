<?php  
 
namespace App\Http\Controllers\Api\Admin; 

use App\Http\Controllers\Api\Controller; 
use App\Http\Requests\Api\SuperAdmin\OrganizationUpdateRequest;
use App\Http\Resources\Admin\AdminOrganizationResource; 
use App\Models\Organization; 
use App\Support\Query\ApiQueryBuilder; 
use Illuminate\Http\JsonResponse; 
use Illuminate\Http\Request; 

class OrganizationController extends Controller 
{ 
    public function index(Request $request): JsonResponse 
    { 
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'Admin account is not assigned to an organization.',
            ], 403);
        }

        $query = Organization::query()
            ->whereKey($organizationId)
            ->with('organizationType'); 
 
        ApiQueryBuilder::applySearch( 
            $query, 
            $request->string('search')->toString(), 
            ['name', 'contact_email', 'abn', 'acn'] 
        );
 
        if ($status = $request->input('filters.status')) { 
            if ($status === 'Active') { 
                $query->whereNull('deleted_at'); 
            } 
 
            if ($status === 'Blocked') { 
                $query->whereNotNull('deleted_at'); 
            } 
        } 
 
        if ($type = $request->input('filters.type')) { 
            $query->whereHas('organizationType', static function ($typeQuery) use ($type): void { 
                $typeQuery->where('name', $type); 
            }); 
        } 
 
        ApiQueryBuilder::applySort( 
            $query, 
            $request->input('sortBy'), 
            $request->input('sortOrder', 'desc'), 
            [ 
                'id' => 'id', 
                'name' => 'name', 
                'email' => 'contact_email', 
                'status' => 'deleted_at', 
                'created_at' => 'created_at', 
                'updated_at' => 'updated_at', 
            ], 
            'created_at' 
        );
 
        $organizations = $query->paginate( 
            ApiQueryBuilder::normalizePerPage($request->integer('pageSize'), 10, 100) 
        ); 
 
        return $this->paginated( 
            AdminOrganizationResource::collection($organizations)->resolve(), 
            $organizations, 
            'Organizations retrieved successfully.' 
        ); 
    } 
 
    public function show(Organization $organization): JsonResponse 
    { 
        $organizationId = request()->user()?->organization_id;

        abort_unless($organizationId && $organization->id === $organizationId, 403);

        $organization->loadMissing('organizationType'); 
 
        return $this->success( 
            new AdminOrganizationResource($organization), 
            'Organization retrieved successfully.' 
        ); 
    } 

    public function update(OrganizationUpdateRequest $request, Organization $organization): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        abort_unless($organizationId && $organization->id === $organizationId, 403);

        $validated = $request->validated();

        if (array_key_exists('abn', $validated)) {
            $validated['abn'] = preg_replace('/\s+/', '', (string) $validated['abn']);
        }

        $organization->fill($validated);
        $organization->save();
        $organization->loadMissing('organizationType');

        return $this->success(
            new AdminOrganizationResource($organization),
            'Organization updated successfully.'
        );
    }
} 
