<?php

namespace App\Services;

use App\Exceptions\DynamicIdConfigurationNotFoundException;
use App\Models\DynamicIdSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Throwable;

class DynamicIdGeneratorService
{
    /**
     * Generate the next immutable identifier for a configured module.
     *
     * Transactions are required so the counter update and returned ID are atomic.
     * Row locking prevents concurrent requests from reading the same counter value.
     * Fallback IDs are prohibited because they create hidden collision paths and
     * break the single-source-of-truth contract for all generated identifiers.
     */
    public function generate(string $entityType): string
    {
        $attempts = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($entityType): string {
                    /** @var DynamicIdSetting|null $locked */
                    $locked = DynamicIdSetting::query()
                        ->where('entity_type', $entityType)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if (!$locked) {
                        throw new DynamicIdConfigurationNotFoundException($entityType);
                    }

                    if ($this->shouldReset($locked)) {
                        $locked->current_number = max(0, $locked->starting_number - 1);
                        $locked->last_reset_at = now();
                    }

                    $highestExistingNumber = $this->highestExistingNumber($entityType, $locked);
                    $baselineNumber = max($locked->current_number, $locked->starting_number - 1, $highestExistingNumber);
                    $nextNumber = $baselineNumber + 1;
                    $locked->current_number = $nextNumber;
                    $locked->save();

                    return $this->format($locked, $nextNumber);
                });
            } catch (Throwable $throwable) {
                if (!$this->isRetryableConcurrencyException($throwable) || $attempts >= 3) {
                    throw $throwable;
                }

                $attempts++;
                usleep(50000 * $attempts);
            }
        }
    }

    public function preview(DynamicIdSetting $setting, ?int $number = null): string
    {
        $previewNumber = $number ?? max($setting->current_number, $setting->starting_number - 1) + 1;

        return $this->format($setting, $previewNumber);
    }

    public function sampleForEntity(string $entityType): ?string
    {
        return $this->preview($this->resolveSetting($entityType));
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

    private function resolveSetting(string $entityType): DynamicIdSetting
    {
        $setting = DynamicIdSetting::query()
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->first();

        if (!$setting) {
            throw new DynamicIdConfigurationNotFoundException($entityType);
        }

        return $setting;
    }

    private function format(DynamicIdSetting $setting, int $number): string
    {
        $parts = [];

        if ($setting->prefix) {
            $parts[] = $setting->prefix;
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

    private function highestExistingNumber(string $entityType, DynamicIdSetting $setting): int
    {
        $table = $this->resolveTableName($entityType);

        if (!$table || !Schema::hasTable($table) || !Schema::hasColumn($table, 'generated_id')) {
            return 0;
        }

        $basePrefix = $this->formatPrefix($setting);
        $query = DB::table($table)->whereNotNull('generated_id');

        if ($basePrefix !== '') {
            $query->where('generated_id', 'like', $basePrefix.$setting->separator.'%');
        }

        $latestGeneratedId = $query
            ->orderByDesc('generated_id')
            ->value('generated_id');

        if (!is_string($latestGeneratedId) || $latestGeneratedId === '') {
            return 0;
        }

        $separator = $setting->separator ?: '-';
        $parts = explode($separator, $latestGeneratedId);
        $lastPart = $parts !== [] ? (string) end($parts) : '';

        return is_numeric($lastPart) ? (int) $lastPart : 0;
    }

    private function formatPrefix(DynamicIdSetting $setting): string
    {
        $parts = [];

        if ($setting->prefix) {
            $parts[] = $setting->prefix;
        }

        if ($setting->include_year) {
            $parts[] = now()->format('Y');
        }

        if ($setting->include_month) {
            $parts[] = now()->format('m');
        }

        return implode($setting->separator ?: '-', array_filter($parts, fn ($part) => $part !== ''));
    }

    private function resolveTableName(string $entityType): ?string
    {
        return match ($entityType) {
            'organizations' => 'organizations',
            'users' => 'users',
            'employees' => 'employees',
            'properties' => 'property_listings',
            'services' => 'services',
            'jobs' => 'jobs',
            'bookings' => 'bookings',
            'quotes' => 'quotes',
            'invoices' => 'invoices',
            'referrals' => 'referrals',
            'orders' => 'orders',
            'inquiries' => 'inquiries',
            'plan_requests' => 'plan_requests',
            default => null,
        };
    }

    private function isRetryableConcurrencyException(Throwable $throwable): bool
    {
        if ($throwable instanceof QueryException) {
            $sqlState = (string) $throwable->getCode();
            $driverCode = $throwable->errorInfo[1] ?? null;

            if (in_array($sqlState, ['5', '1213', '1205', '40001'], true)) {
                return true;
            }

            if (in_array($driverCode, [5, 1205, 1213], true)) {
                return true;
            }

            $message = strtolower($throwable->getMessage());

            return str_contains($message, 'database is locked')
                || str_contains($message, 'deadlock')
                || str_contains($message, 'lock wait timeout');
        }

        return $throwable->getPrevious() ? $this->isRetryableConcurrencyException($throwable->getPrevious()) : false;
    }
}
