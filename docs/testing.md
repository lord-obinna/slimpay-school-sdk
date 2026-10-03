# Testing

## Sign requests yourself

`Signature::sign()` produces the same header Slimpay does, so you can simulate Slimpay in tests or from `curl`.

```php
use Slimpay\School\Signature;

$body = json_encode(['school_id' => 'SCH-1', 'student_id' => 'STU-2201']);
$ts   = (string) time();
$headers = [
    'X-Slimpay-Timestamp' => $ts,
    'X-Slimpay-Signature' => Signature::sign($ts, $body, $secret),
];
```

### From the command line

```bash
BODY='{"school_id":"SCH-1","student_id":"STU-2201"}'
TS=$(date +%s)
SIG=$(printf '%s' "$TS.$BODY" | openssl dgst -sha256 -hmac "$SECRET" -hex | sed 's/^.* //')

curl -i https://your-site.test/slimpay/student-lookup \
  -H "Content-Type: application/json" \
  -H "X-Slimpay-Timestamp: $TS" -H "X-Slimpay-Signature: $SIG" \
  -d "$BODY"
```

## PHPUnit example (Laravel)

```php
public function test_student_lookup_returns_student(): void
{
    config(['slimpay.student_lookup_secret' => 'secret', 'slimpay.student_lookup' => FakeStudents::class]);

    $body = json_encode(['school_id' => 'SCH-1', 'student_id' => 'STU-1']);
    $ts = (string) time();

    $this->call('POST', '/slimpay/student-lookup', [], [], [], [
        'HTTP_X_SLIMPAY_TIMESTAMP' => $ts,
        'HTTP_X_SLIMPAY_SIGNATURE' => Signature::sign($ts, $body, 'secret'),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk()->assertJson(['found' => true]);
}

public function test_forged_request_is_rejected(): void
{
    $this->postJson('/slimpay/student-lookup', ['student_id' => 'STU-1'])->assertStatus(401);
}
```

## Test your webhook handler

```php
$handler = (new WebhookHandler('secret'))->on('payment.received', fn ($e) => $paid[] = $e->checkoutReference());

$body = json_encode(['event' => 'payment.received', 'checkout_reference' => 'SCH-ABC', 'amount' => 25000]);
$ts = (string) time();
$handler->handle($body, ['X-Slimpay-Timestamp' => $ts, 'X-Slimpay-Signature' => Signature::sign($ts, $body, 'secret')]);
```

Also test: the same event twice (idempotency) and a `SCH-TEST…` reference (ignored).

## Fake the API client

Inject a transport so no network is used:

```php
$client = new Client('key', transport: fn () => [200, json_encode(['status' => true, 'data' => [
    'checkout_reference' => 'SCH-1', 'checkout_url' => 'https://pay.test', 'amount' => 1000,
    'split' => ['school_receives' => 985, 'platform_fee' => 15],
]])]);
```

## Developing locally

Slimpay can only call public HTTPS URLs. Expose your dev server with a tunnel (`ngrok http 8000`, Cloudflare Tunnel) and paste the tunnel URL into the Slimpay portal. Slimpay blocks private and loopback addresses on purpose.

## Run the SDK's own tests

```bash
composer install
composer test
```
