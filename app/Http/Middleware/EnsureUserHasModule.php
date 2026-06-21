<?php

namespace App\Http\Middleware;

use App\Support\Business\BusinessModuleResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasModule
{
    public function __construct(private readonly BusinessModuleResolver $moduleResolver)
    {
    }

    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        $allowedModules = $this->moduleResolver->resolve($user);
        $required = collect($modules)
            ->flatMap(fn (string $value): array => array_filter(explode('|', $value)))
            ->values()
            ->all();

        if ($required !== [] && !collect($required)->some(fn (string $module): bool => in_array($module, $allowedModules, true))) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Missing required business module.',
            ], 403);
        }

        return $next($request);
    }
}
