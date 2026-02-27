<?php

namespace App\Exceptions\Sika;

use Exception;
use Throwable;

class PbgApiException extends Exception
{
    protected array $responseData;

    public function __construct(
        string $message = 'Priority Bank API error',
        int $code = 500,
        ?Throwable $previous = null,
        array $responseData = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->responseData = $responseData;
    }

    public function getResponseData(): array
    {
        return $this->responseData;
    }
}
