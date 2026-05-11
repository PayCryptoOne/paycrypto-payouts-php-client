<?php

declare(strict_types=1);

namespace PayCrypto\Payouts\Client;

final class PayoutApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly mixed $responseBody,
    ) {
        parent::__construct($message);
    }
}
