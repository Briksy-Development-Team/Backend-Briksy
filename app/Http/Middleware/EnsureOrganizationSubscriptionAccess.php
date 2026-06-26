<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationSubscriptionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof User || $user->hasRole('super_admin')) {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        if ($user->hasActiveSubscriptionAccess()) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'code' => 'subscription_required',
            'message' => 'Your free trial has ended. Please subscribe to continue.',
        ], 402);
    }

    private function isExempt(Request $request): bool
    {
        $path = $request->path();

        return $request->is('api/me/permissions')
            || $request->is('api/admin/auth/me')
            || $request->is('api/admin/auth/logout')
            || $request->is('api/admin/plans')
            || $request->is('api/admin/plans/*')
            || $request->is('api/admin/subscription*')
            || $path === 'api/admin/auth/login'
            || $path === 'api/admin/auth/register'
            || $path === 'api/admin/auth/register-staff';
    }
}
