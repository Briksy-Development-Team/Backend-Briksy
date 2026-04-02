<?php  
 
namespace App\Http\Controllers\Api\Admin; 
 
use App\Http\Controllers\Api\Controller; 
use App\Http\Resources\Admin\AdminStaffResource; 
use App\Models\Role; 
use App\Models\User; 
use App\Support\Query\ApiQueryBuilder; 
use Illuminate\Http\JsonResponse; 
use Illuminate\Http\Request; 
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Str; 
 
class StaffController extends Controller 
{ 
    public function index(Request $request): JsonResponse 
    { 
        $query = User::query()->with('roles')->whereHas('roles', static function ($roleQuery): void { $roleQuery->where('roles.name', 'admin_staff'); }); 
        ApiQueryBuilder::applySearch($query, $request->string('search')->toString(), ['name', 'email']); 
        $staff = $query->orderByDesc('created_at')->paginate(ApiQueryBuilder::normalizePerPage($request->integer('items_per_page'), 10, 100)); 
        return response()->json(['data' => AdminStaffResource::collection($staff)->resolve(), 'payload' => ['pagination' => ['page' => $staff->currentPage(), 'items_per_page' => (int) $staff->perPage(), 'links' => []]]]); 
    } 
 
    public function show(User $user): JsonResponse 
    { 
        abort_unless($user->hasRole('admin_staff') || $user->hasRole('admin'), 404); 
        return response()->json(['data' => (new AdminStaffResource($user->loadMissing('roles')))->resolve()]); 
    }
 
    public function store(Request $request): JsonResponse 
    { 
        $validated = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:150', 'unique:users,email'], 'role' => ['nullable', 'string']]); 
        $staff = DB::transaction(function () use ($validated): User { 
            $staff = User::create(['name' => $validated['name'], 'email' => $validated['email'], 'password_hash' => Str::password(16), 'organization_id' => null, 'id_verified' => false]); 
            $role = Role::query()->firstOrCreate(['name' => 'admin_staff'], ['scope' => 'tenant', 'is_system' => true]); 
            $staff->roles()->syncWithoutDetaching([$role->id => ['id' => (string) str()->uuid(), 'organization_id' => null]]); 
            return $staff->load('roles'); 
        }); 
        return $this->created((new AdminStaffResource($staff))->resolve(), 'Staff member created successfully.'); 
    } 
 
    public function update(Request $request, User $user): JsonResponse 
    { 
        abort_unless($user->hasRole('admin_staff') || $user->hasRole('admin'), 404); 
        $validated = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:150', 'unique:users,email,' . $user->id]]); 
        $user->update($validated); 
        return $this->success((new AdminStaffResource($user->fresh('roles')))->resolve(), 'Staff member updated successfully.'); 
    } 
 
    public function destroy(User $user): JsonResponse 
    { 
        abort_unless($user->hasRole('admin_staff') || $user->hasRole('admin'), 404); 
        $user->delete(); 
        return $this->success([], 'Staff member deleted successfully.'); 
    } 
}
