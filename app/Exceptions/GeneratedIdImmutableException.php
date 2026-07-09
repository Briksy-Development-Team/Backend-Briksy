<?php

namespace App\Exceptions;

use RuntimeException;

class GeneratedIdImmutableException extends RuntimeException
{
    public function __construct(string $model, ?\Throwable $previous = null)
    {
        parent::__construct("Generated ID is immutable on {$model}.", 422, $previous);
    }
}
