<?php

namespace App\Exceptions;

use RuntimeException;
use Illuminate\Support\Str;

class DynamicIdConfigurationNotFoundException extends RuntimeException
{
    public function __construct(string $module, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Dynamic ID configuration not found for module: %s.', Str::headline($module)),
            500,
            $previous
        );
    }
}
