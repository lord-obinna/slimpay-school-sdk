# Webhooks

Slimpay POSTs a JSON notification to your webhook URL when a school-fee payment is confirmed.

## Handling them

```php
use Slimpay\School\{WebhookHandler, WebhookEvent};

$webhooks = (new WebhookHandler(getenv('SLIMPAY_WEBHOOK_SECRET')))
    ->on('payment.received', function (WebhookEvent $e) {
        if ($e->isTest()) {
            return;                                   // ignore "Send Test Webhook" pings
        }
        markFeePaid(
            studentId: $e->studentId(),
            amount:    $e->amount(),
            reference: $e->checkoutReference(),
        );
    });

$webhooks->handle(file_get_contents('php://input'), getallheaders());
http_response_code(200);
```

- `on('*', …)` receives every event type.
- `parse()` verifies and returns the event without calling listeners if you prefer to route yourself.
- Respond with **any 2xx** quickly. Do heavy work in a queue.

## Payload reference

Event `payment.received`:

```json
{
  "event": "payment.received",
  "checkout_reference": "SCH-XXXXXXXXXXXX",
  "student_name": "Jane Doe",
  "student_id": "STU-2201",
  "student_class": "SS3",
  "student_verified": true,
  "verified_student_data": { "name": "Jane Doe", "class": "SS3" },
  "amount": 25000,
  "school_net": 24625,
  "platform_fee": 375,
  "timestamp": "2026-07-21T10:32:00+00:00"
}
```

| Field | Notes |
|---|---|
| `checkout_reference` | Unique per payment. **Use it as your idempotency key.** |
| `student_id`, `student_name`, `student_class` | As typed by the payer, or from your lookup when verified |
| `student_verified` / `verified_student_data` | Only populated when student lookup confirmed the student; otherwise `false` / `null` |
| `amount` | Total paid, in naira |
| `school_net` / `platform_fee` | Your share and Slimpay's fee |
| `timestamp` | ISO-8601 time of the event |

Some payloads (for example older flows) carry `transaction_id` instead of `checkout_reference`; `WebhookEvent::checkoutReference()` returns whichever is present. Every field is also available in `$event->payload`.

## Signatures

When a signing secret exists for your school, each webhook carries `X-Slimpay-Timestamp` and `X-Slimpay-Signature`, the same scheme as lookups ([security.md](security.md)). Pass the secret to `WebhookHandler` and unsigned or forged requests are rejected with `InvalidSignatureException` (respond `401`).

If you have **not** generated a webhook secret yet, webhooks are sent unsigned. Generate one on the Webhooks page, then pass it to the handler. Until you do, treat webhooks as hints and verify what matters (payment status, amount) before releasing anything valuable.

## Idempotency

Delivery is **at most once with manual resend**: Slimpay does not auto-retry, but you can press *Resend* on a delivery, and a resend re-sends the same payload. Your handler must therefore tolerate duplicates:

```php
->on('payment.received', function (WebhookEvent $e) {
    DB::transaction(function () use ($e) {
        // unique index on payments.slimpay_reference
        if (Payment::where('slimpay_reference', $e->checkoutReference())->exists()) {
            return;
        }
        Payment::create([...]);
    });
});
```

## Failure behaviour

| Your response | What Slimpay records |
|---|---|
| 2xx | Delivered |
| non-2xx, timeout (5 s), DNS or TLS error | Failed, with the status or error shown in the delivery log |

Fix your endpoint, then use **Resend** on the failed delivery. It goes to your *current* webhook URL.

## Testing

Webhooks page → **Send Test Webhook** sends a sample `payment.received` with a `SCH-TEST…` reference, which `$event->isTest()` detects. See [testing.md](testing.md) to simulate signed webhooks in your test suite.
