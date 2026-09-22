<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class WalletWebhookRetryableException extends RuntimeException
{
    public function __construct(
        public readonly ?int $statusCode = null,
        string $message = 'Wallet webhook delivery failed, retrying.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
