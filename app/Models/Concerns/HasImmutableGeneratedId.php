<?php

namespace App\Models\Concerns;

use App\Exceptions\GeneratedIdImmutableException;

trait HasImmutableGeneratedId
{
    protected static function bootHasImmutableGeneratedId(): void
    {
        static::updating(function ($model): void {
            if ($model->isDirty('generated_id')) {
                throw new GeneratedIdImmutableException(class_basename($model));
            }
        });
    }
}
