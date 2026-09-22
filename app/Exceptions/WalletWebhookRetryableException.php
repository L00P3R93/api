<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class WalletWebhookRetryableException extends RuntimeException
{
    public function __construct(
        public readonly ?int $statusCode = null,
        public readonly ?string $responseBody = null,
        ?string $message = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?? ($statusCode ? "Wallet webhook delivery failed, retrying (status {$statusCode})." : 'Wallet webhook delivery failed (connection error), retrying.'),
            previous: $previous
        );
    }
}
