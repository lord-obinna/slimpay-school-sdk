<?php

namespace Slimpay\School;

/** A payment checkout created by Client::collectInvoice(). Send the payer to $url. */
final class Checkout
{
    public function __construct(
        public readonly string $reference,
        public readonly ?string $url,
        public readonly ?string $invoiceId,
        public readonly float $amount,
        public readonly float $schoolReceives,
        public readonly float $platformFee,
        public readonly array $student,
        public readonly ?string $expiresAt,
    ) {
    }

    /** @param array<string, mixed> $data The "data" object of the API response. */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['checkout_reference'] ?? ''),
            $data['checkout_url'] ?? null,
            $data['invoice_id'] ?? null,
            (float) ($data['amount'] ?? 0),
            (float) ($data['split']['school_receives'] ?? 0),
            (float) ($data['split']['platform_fee'] ?? 0),
            $data['student'] ?? [],
            $data['expires_at'] ?? null,
        );
    }
}
