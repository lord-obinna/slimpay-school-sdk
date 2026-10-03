<?php

namespace Slimpay\School;

use Slimpay\School\Exceptions\InvalidSignatureException;
use Slimpay\School\Exceptions\MalformedRequestException;

/**
 * Parses (and, when a secret is set, verifies) Slimpay webhooks, then fires your listeners.
 *
 *   $webhooks->on('payment.received', fn (WebhookEvent $e) => markInvoicePaid($e->payload));
 */
final class WebhookHandler
{
    /** @var array<string, list<callable(WebhookEvent): void>> */
    private array $listeners = [];

    /**
     * @param ?string $secret When set, requests must carry a valid signature (same scheme as lookups).
     *                        Leave null only while Slimpay's webhooks are unsigned for your account.
     */
    public function __construct(
        private ?string $secret = null,
        private int $tolerance = Signature::DEFAULT_TOLERANCE,
    ) {
    }

    /** @param callable(WebhookEvent): void $listener  Use '*' to receive every event. */
    public function on(string $eventType, callable $listener): self
    {
        $this->listeners[$eventType][] = $listener;
        return $this;
    }

    /**
     * @param array<string, string|string[]> $headers
     * @throws InvalidSignatureException|MalformedRequestException
     */
    public function parse(string $rawBody, array $headers = []): WebhookEvent
    {
        if ($this->secret !== null && $this->secret !== '') {
            Signature::verify($rawBody, $headers, $this->secret, $this->tolerance);
        }

        $payload = json_decode($rawBody, true);

        if (!is_array($payload) || !isset($payload['event']) || !is_string($payload['event'])) {
            throw new MalformedRequestException('Webhook body must be JSON with an "event" field.');
        }

        return new WebhookEvent($payload['event'], $payload);
    }

    /**
     * Parse, then call matching listeners. Return a 2xx afterwards, or Slimpay logs a failed delivery.
     *
     * @param array<string, string|string[]> $headers
     */
    public function handle(string $rawBody, array $headers = []): WebhookEvent
    {
        $event = $this->parse($rawBody, $headers);

        foreach (array_merge($this->listeners[$event->type] ?? [], $this->listeners['*'] ?? []) as $listener) {
            $listener($event);
        }

        return $event;
    }
}
