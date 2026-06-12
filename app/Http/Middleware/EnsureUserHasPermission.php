<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $required = collect($permissions)
            ->flatMap(fn (string $value): array => array_filter(explode('|', $value)))
            ->values()
            ->all();

        if ($required !== [] && !$user->hasAnyPermission($required)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Missing required permission.',
            ], 403);
        }

        return $next($request);
    }
}
