<?php

namespace App\Exceptions;

use RuntimeException;

class MpesaApiException extends RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly ?int $statusCode = null,
        public readonly ?string $errorCode = null,
        public readonly ?array $responseBody = null,
    ) {
        parent::__construct($message);
    }
}
