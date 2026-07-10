<?php

namespace App\Exceptions;

class AbnLookupVerificationException extends AbnLookupException
{
    public function __construct(string $message = 'Invalid or inactive Australian Business Number.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 400, 0, $previous);
    }
}
