<?php

namespace App\Services;

use App\Models\DynamicIdSetting;
use Illuminate\Support\Facades\DB;

class DynamicIdGeneratorService
{
    public function generate(string $entityType, ?string $fallbackPrefix = null): ?string
    {
        $setting = DynamicIdSetting::query()
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->first();

        if (!$setting) {
            return null;
        }

        return DB::transaction(function () use ($setting, $fallbackPrefix): string {
            /** @var DynamicIdSetting $locked */
            $locked = DynamicIdSetting::query()
                ->whereKey($setting->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->shouldReset($locked)) {
                $locked->current_number = max(0, $locked->starting_number - 1);
                $locked->last_reset_at = now();
            }

            $nextNumber = max($locked->current_number, $locked->starting_number - 1) + 1;
            $locked->current_number = $nextNumber;
            $locked->save();

            return $this->format($locked, $nextNumber, $fallbackPrefix);
        });
    }

    public function preview(DynamicIdSetting $setting, ?int $number = null): string
    {
        $previewNumber = $number ?? max($setting->current_number, $setting->starting_number - 1) + 1;

        return $this->format($setting, $previewNumber);
    }

    public function sampleForEntity(string $entityType, ?string $fallbackPrefix = null): ?string
    {
        $setting = DynamicIdSetting::query()->where('entity_type', $entityType)->first();

        if (!$setting) {
            return null;
        }

        return $this->preview($setting);
    }

    private function shouldReset(DynamicIdSetting $setting): bool
    {
        if ($setting->reset_frequency === 'none' || !$setting->last_reset_at) {
            return false;
        }

        return match ($setting->reset_frequency) {
            'monthly' => !$setting->last_reset_at->isSameMonth(now()),
            'yearly' => !$setting->last_reset_at->isSameYear(now()),
            default => false,
        };
    }

    private function format(DynamicIdSetting $setting, int $number, ?string $fallbackPrefix = null): string
    {
        $parts = [];
        $prefix = $setting->prefix ?: $fallbackPrefix;

        if ($prefix) {
            $parts[] = $prefix;
        }

        if ($setting->include_year) {
            $parts[] = now()->format('Y');
        }

        if ($setting->include_month) {
            $parts[] = now()->format('m');
        }

        $parts[] = str_pad((string) $number, max(1, $setting->number_padding), '0', STR_PAD_LEFT);

        return implode($setting->separator ?: '-', array_filter($parts, fn ($part) => $part !== ''));
    }
}
