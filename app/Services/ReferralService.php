<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Str;

class ReferralService
{
    public function generateCode(): string
    {
        do {
            $code = strtoupper('REF-' . Str::random(8));
        } while (Organization::query()->where('referral_code', $code)->exists());

        return $code;
    }

    public function resolveReferrer(?string $code): ?Organization
    {
        if (blank($code)) {
            return null;
        }

        return Organization::query()
            ->where('referral_code', trim((string) $code))
            ->first();
    }

    public function referralLink(Organization $organization): string
    {
        $base = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/');
        $code = urlencode((string) $organization->referral_code);

        return "{$base}/auth/registration?ref={$code}";
    }
}
