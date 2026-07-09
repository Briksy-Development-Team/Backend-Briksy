<?php

namespace App\Exceptions;

use RuntimeException;

abstract class AbnLookupException extends RuntimeException
{
    public function __construct(
        string $message,
        protected readonly int $httpStatus = 400,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
