<?php

namespace App\Exceptions;

class AbnLookupUnavailableException extends AbnLookupException
{
    public function __construct(string $message = 'Unable to verify your ABN at the moment. Please try again shortly.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 503, 0, $previous);
    }
}
