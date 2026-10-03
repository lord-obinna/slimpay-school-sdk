<?php

namespace Slimpay\School\Exceptions;

/** Slimpay answered with an error (or something unparseable). */
class ApiException extends SlimpayException
{
    /** @param array<string, mixed> $body */
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly ?string $errorCode = null,
        public readonly array $body = [],
    ) {
        parent::__construct($message, $httpStatus);
    }
}
