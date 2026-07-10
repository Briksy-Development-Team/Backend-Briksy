<?php

namespace App\Services;

use App\Models\Organization;

class ReferralService
{
    public function __construct(
        private readonly DynamicIdGeneratorService $idGenerator
    ) {
    }

    public function generateCode(): string
    {
        return $this->idGenerator->generate('referrals');
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
