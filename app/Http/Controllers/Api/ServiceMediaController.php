<?php

namespace App\Http\Controllers\Api;

use App\Models\ServiceMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceMediaController extends Controller
{
    public function show(ServiceMedia $serviceMedia)
    {
        $rawUrl = (string) $serviceMedia->file_url;
        if (filter_var($rawUrl, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $rawUrl)) {
            return redirect()->away($rawUrl);
        }

        $path = ltrim($rawUrl, '/');
        $path = preg_replace('#^storage/#', '', $path) ?? $path;
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function destroy(Request $request, ServiceMedia $serviceMedia)
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($user->isSuperAdmin() || $user->isGlobalStaff() || $user->organization_id === $serviceMedia->service?->organization_id, 403);

        $path = preg_replace('#^storage/#', '', ltrim((string) $serviceMedia->file_url, '/')) ?? '';
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
        $serviceMedia->delete();

        return response()->json(['success' => true, 'message' => 'Service media deleted successfully.']);
    }
}
