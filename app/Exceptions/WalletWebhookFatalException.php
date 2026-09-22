<?php

namespace App\Exceptions;

use RuntimeException;

class WalletWebhookFatalException extends RuntimeException
{
    public function __construct(
        public readonly ?int $statusCode = null,
        string $message = 'Wallet webhook delivery failed with a non-retryable response.',
    ) {
        parent::__construct($message);
    }
}
