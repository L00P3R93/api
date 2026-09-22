<?php

namespace App\Exceptions;

use RuntimeException;

class WalletWebhookFatalException extends RuntimeException
{
    public function __construct(
        public readonly ?int $statusCode = null,
        public readonly ?string $responseBody = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? "Wallet webhook delivery failed with a non-retryable response (status {$statusCode}).");
    }
}
