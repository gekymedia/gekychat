<?php

namespace App\Exceptions\Sika;

class PbgInsufficientFundsException extends PbgApiException
{
    public function __construct(
        string $message = 'Insufficient funds in Priority Bank wallet',
        int $code = 402,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
