# API client

`Slimpay\School\Client` calls Slimpay on behalf of your school using your API key.

```php
use Slimpay\School\Client;

$slimpay = new Client(
    apiKey:  getenv('SLIMPAY_API_KEY'),                 // sandbox or live
    baseUrl: 'https://api.slimpay.ng/api/v1',          // default, shown for clarity
);
```

The key decides the environment: a sandbox key hits the sandbox, a live key is real money. Keep the live key server-side only.

## `collectInvoice()`

Start a payment for an invoice that lives in your system.

```php
$checkout = $slimpay->collectInvoice(
    schoolId:    'SCH-9K2H7XQZ',
    invoiceId:   'INV-8841',
    redirectUrl: 'https://portal.example.edu.ng/payments/return',
);

return redirect($checkout->url);
```

What happens:

1. Slimpay validates your key and `school_id`.
2. Slimpay calls your **invoice lookup** ([lookups.md](lookups.md)) to get the amount and student.
3. A checkout is created and returned. The payer pays on it, then returns to your `redirectUrl`.
4. Slimpay sends a `payment.received` webhook ([webhooks.md](webhooks.md)).

**Never trust the redirect alone** to mark an invoice paid. Wait for the webhook.

### The `Checkout` object

| Property | Meaning |
|---|---|
| `reference` | Slimpay checkout reference (`SCH-…`), matches `checkout_reference` in the webhook |
| `url` | Send the payer here |
| `invoiceId` | Echo of your invoice id |
| `amount` | Total the payer pays |
| `schoolReceives` / `platformFee` | The split |
| `student` | `student_id`, `name`, `class` as returned by your lookup |
| `expiresAt` | ISO-8601; checkouts last 30 minutes |

### Retrying is safe

Calling `collectInvoice()` again for the same invoice while a checkout is unexpired returns the **same** checkout instead of creating another, so double-clicks and network retries won't create duplicate payment links.

### Redirect URL

`redirectUrl` is required and must be a valid URL: the payer is never logged in to Slimpay, so Slimpay needs to know where to send them back to.

## Errors

Failures throw `Slimpay\School\Exceptions\ApiException`:

```php
use Slimpay\School\Exceptions\ApiException;

try {
    $checkout = $slimpay->collectInvoice(...);
} catch (ApiException $e) {
    $e->getMessage();   // human-readable
    $e->httpStatus;     // 0 when Slimpay couldn't be reached
    $e->errorCode;      // machine-readable, see below (may be null)
    $e->body;           // full decoded response
}
```

| `errorCode` | HTTP | Meaning | What to do |
|---|---|---|---|
| `SETTLEMENT_PENDING` | 422 | Bank/settlement account not approved yet | Finish setup in the Slimpay portal |
| `INVOICE_LOOKUP_NOT_CONFIGURED` | 422 | No invoice lookup URL/secret saved | Configure it in Settings |
| `INVOICE_NOT_FOUND` | 404 | Your endpoint returned not-found, timed out or answered badly | Check your endpoint and its logs |
| `INVOICE_NOT_COLLECTIBLE` | 422 | Invoice `status` isn't unpaid/pending/due | Don't collect settled invoices |
| `INVOICE_COLLECT_LOCKED` | 409 | Another request for this invoice is in flight | Retry in a moment |
| *(none)*, 401 | 401 | Missing or invalid API key | Check `SLIMPAY_API_KEY` |
| *(none)*, 403 | 403 | `school_id` doesn't belong to this key | Use your own school id |
| *(none)*, 422 | 422 | Validation failure, or amount under ₦100 | Read `getMessage()` |

Rate limits apply to financial endpoints; back off and retry on HTTP 429.

## Custom HTTP transport

By default the client uses `ext-curl`. To use Guzzle, Symfony HttpClient or a test double, pass a callable returning `[statusCode, rawBody]`:

```php
$slimpay = new Client($key, transport: function (string $method, string $url, array $headers, ?string $body): array {
    $res = $guzzle->request($method, $url, ['headers' => $headers, 'body' => $body, 'http_errors' => false]);
    return [$res->getStatusCode(), (string) $res->getBody()];
});
```
