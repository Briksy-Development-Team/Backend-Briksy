<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Admin\AdminSeekerResource;
use App\Models\User;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeekerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with('seekerProfile')
            ->whereHas('roles', static function ($roleQuery): void {
                $roleQuery->where('roles.name', 'seeker');
            });

        ApiQueryBuilder::applySearch(
            $query,
            $request->string('search')->toString(),
            ['name', 'email', 'display_name', 'mobile_number']
        );

        if ($status = $request->input('filters.status')) {
            if ($status === 'Active') {
                $query->whereNull('deleted_at');
            }

            if ($status === 'Inactive') {
                $query->whereNotNull('deleted_at');
            }
        }

        if ($location = $request->input('filters.location')) {
            $query->whereHas('seekerProfile', static function ($profileQuery) use ($location): void {
                $profileQuery->where('current_postcode', $location);
            });
        }

        if ($createdFrom = $request->input('filters.created_at.from')) {
            $query->whereDate('created_at', '>=', $createdFrom);
        }

        if ($createdTo = $request->input('filters.created_at.to')) {
            $query->whereDate('created_at', '<=', $createdTo);
        }

        if ($updatedFrom = $request->input('filters.updated_at.from')) {
            $query->whereDate('updated_at', '>=', $updatedFrom);
        }

        if ($updatedTo = $request->input('filters.updated_at.to')) {
            $query->whereDate('updated_at', '<=', $updatedTo);
        }

        ApiQueryBuilder::applySort(
            $query,
            $request->input('sortBy'),
            $request->input('sortOrder', 'desc'),
            [
                'id' => 'id',
                'name' => 'name',
                'email' => 'email',
                'created_at' => 'created_at',
                'updated_at' => 'updated_at',
            ],
            'created_at'
        );

        $seekers = $query->paginate(
            ApiQueryBuilder::normalizePerPage($request->integer('pageSize'), 10, 100)
        );

        return $this->paginated(
            AdminSeekerResource::collection($seekers)->resolve(),
            $seekers,
            'Seekers retrieved successfully.'
        );
    }

    public function show(User $user): JsonResponse
    {
        abort_unless($user->hasRole('seeker'), 404);

        $user->loadMissing('seekerProfile');

        return $this->success(
            new AdminSeekerResource($user),
            'Seeker retrieved successfully.'
        );
    }
}
