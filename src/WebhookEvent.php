<?php

namespace Slimpay\School;

/** A payment notification Slimpay POSTed to your webhook URL. */
final class WebhookEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly string $type,
        public readonly array $payload,
    ) {
    }

    public function isPaymentReceived(): bool
    {
        return $this->type === 'payment.received';
    }

    public function checkoutReference(): ?string
    {
        return $this->payload['checkout_reference'] ?? $this->payload['transaction_id'] ?? null;
    }

    public function studentId(): ?string
    {
        return $this->payload['student_id'] ?? null;
    }

    public function amount(): float
    {
        return (float) ($this->payload['amount'] ?? 0);
    }

    /** Test pings from the portal's "Send Test Webhook" button carry an SCH-TEST reference. */
    public function isTest(): bool
    {
        return str_starts_with((string) $this->checkoutReference(), 'SCH-TEST');
    }
}
