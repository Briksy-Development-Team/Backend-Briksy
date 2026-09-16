<?php

namespace App\Http\Controllers\Api;

use App\Models\Organization;
use Illuminate\Support\Facades\Storage;

class OrganizationMediaController extends Controller
{
    public function show(Organization $organization, string $type)
    {
        abort_unless(in_array($type, ['profile', 'banner'], true), 404);

        $path = $type === 'profile' ? $organization->logo_url : $organization->banner_url;
        $path = preg_replace('#^storage/#', '', ltrim((string) $path, '/')) ?? '';

        abort_unless($path !== '' && Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'public, max-age=86400, immutable',
        ]);
    }
}
