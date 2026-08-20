<?php

namespace App\Exceptions;

use RuntimeException;

class GoogleAuthException extends RuntimeException
{
    public function __construct(string $message = 'Google authentication required.', int $code = 401, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
