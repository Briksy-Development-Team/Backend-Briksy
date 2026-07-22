<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMediaRequest;
use App\Http\Requests\UpdateMediaRequest;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMediaRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Media $media)
    {
        $path = $media->file_url;

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $path = parse_url($path, PHP_URL_PATH) ?: $path;
        }

        $path = ltrim((string) $path, '/');
        $path = preg_replace('#^storage/#', '', $path) ?? $path;

        abort_unless($path !== '', 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(
            Storage::disk('public')->path($path),
            [
                'Cache-Control' => 'public, max-age=86400',
            ]
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Media $media)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMediaRequest $request, Media $media)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Media $media)
    {
        $user = $request->user();

        abort_unless($user, 401);

        $propertyListing = $media->propertyListing;
        $canManageListing = $user->isSuperAdmin()
            || $user->isGlobalStaff()
            || ($user->organization_id && $propertyListing?->org_id === $user->organization_id);

        abort_unless($canManageListing, 403);

        $path = $media->file_url;

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $path = parse_url($path, PHP_URL_PATH) ?: $path;
        }

        $path = ltrim((string) $path, '/');
        $path = preg_replace('#^storage/#', '', $path) ?? $path;

        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        $media->delete();

        return response()->json([
            'success' => true,
            'message' => 'Media deleted successfully.',
        ]);
    }
}
