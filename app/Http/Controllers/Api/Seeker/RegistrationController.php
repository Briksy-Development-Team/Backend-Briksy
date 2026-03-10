<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\RegisterSeekerRequest;
use App\Http\Resources\Seeker\SeekerAccountResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegistrationController extends Controller
{
    public function store(RegisterSeekerRequest $request)
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password_hash' => $request->input('password'),
                'organization_id' => null,
                'id_verified' => false,
            ]);

            $seekerRole = Role::query()->firstOrCreate(
                ['name' => 'seeker'],
                ['scope' => 'global', 'is_system' => true]
            );

            $user->roles()->syncWithoutDetaching([
                $seekerRole->id => [
                    'id' => (string) str()->uuid(),
                    'organization_id' => null,
                ],
            ]);

            return $user->load('roles');
        });

        return $this->created(
            new SeekerAccountResource($user),
            'Seeker registered successfully.'
        );
    }
}
