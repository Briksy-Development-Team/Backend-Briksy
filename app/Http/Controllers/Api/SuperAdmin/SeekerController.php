<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\SeekerIndexRequest;
use App\Http\Requests\Api\SuperAdmin\SeekerStoreRequest;
use App\Http\Requests\Api\SuperAdmin\SeekerUpdateRequest;
use App\Http\Resources\SuperAdmin\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeekerController extends Controller
{
    public function index(SeekerIndexRequest $request): JsonResponse
    {
        $query = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'seeker'))
            ->with('roles');

        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());

        $emailVerified = $request->has('filter.email_verified')
            ? $request->boolean('filter.email_verified')
            : null;

        $mobileVerified = $request->has('filter.mobile_verified')
            ? $request->boolean('filter.mobile_verified')
            : null;

        ApiQueryBuilder::applyPresenceFilter($query, 'email_verified_at', $emailVerified);
        ApiQueryBuilder::applyPresenceFilter($query, 'mobile_verified_at', $mobileVerified);

        ApiQueryBuilder::applySort(
            $query,
            $request->sort(),
            $request->direction(),
            $request->allowedSorts(),
            'created_at'
        );

        $paginator = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            UserResource::collection($paginator),
            $paginator,
            'Seekers retrieved successfully.'
        );
    }

    public function show(User $seeker): JsonResponse
    {
        if (!$seeker->hasRole('seeker')) {
            return response()->json([
                'success' => false,
                'message' => 'Seeker not found.',
            ], 404);
        }

        $seeker->load('roles');

        return $this->success(
            new UserResource($seeker),
            'Seeker retrieved successfully.'
        );
    }

    public function store(SeekerStoreRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password_hash' => bcrypt($request->input('password')),
                'mobile_number' => $request->input('mobile_number'),
                'display_name' => $request->input('display_name'),
                'organization_id' => null,
                'id_verified' => false,
            ]);

            $role = Role::query()->firstOrCreate(
                ['name' => 'seeker'],
                ['scope' => 'global', 'is_system' => true]
            );

            $user->roles()->syncWithoutDetaching([
                $role->id => [
                    'id' => (string) Str::uuid(),
                    'organization_id' => null,
                ],
            ]);

            return $user->load('roles');
        });

        return $this->created(
            new UserResource($user),
            'Seeker created successfully.'
        );
    }

    public function update(SeekerUpdateRequest $request, User $seeker): JsonResponse
    {
        if (!$seeker->hasRole('seeker')) {
            return response()->json([
                'success' => false,
                'message' => 'Seeker not found.',
            ], 404);
        }

        $data = $request->validated();

        if (!empty($data['password'])) {
            $data['password_hash'] = bcrypt($data['password']);
        }

        unset($data['password']);

        $seeker->fill($data);
        $seeker->save();

        return $this->success(
            new UserResource($seeker->fresh()->load('roles')),
            'Seeker updated successfully.'
        );
    }

    public function destroy(User $seeker): JsonResponse
    {
        if (!$seeker->hasRole('seeker')) {
            return response()->json([
                'success' => false,
                'message' => 'Seeker not found.',
            ], 404);
        }

        $seeker->delete();

        return $this->success([], 'Seeker deleted successfully.');
    }
}
