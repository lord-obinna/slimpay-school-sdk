# Laravel

Supports Laravel 10, 11 and 12. The package is auto-discovered.

## 1. Install and publish config

```bash
composer require slimpay/school-sdk
php artisan vendor:publish --tag=slimpay-config
```

## 2. Environment

```env
SLIMPAY_STUDENT_LOOKUP_SECRET=
SLIMPAY_INVOICE_LOOKUP_SECRET=
SLIMPAY_WEBHOOK_SECRET=
SLIMPAY_API_KEY=
SLIMPAY_SCHOOL_ID=SCH-XXXXXXXX
```

## 3. Implement your lookups

Create classes implementing `StudentLookup` / `InvoiceLookup` ([lookups.md](lookups.md)), for example in `app/Slimpay/`, and reference them in `config/slimpay.php`:

```php
'student_lookup' => App\Slimpay\Students::class,
'invoice_lookup' => App\Slimpay\Invoices::class,
```

They're resolved from the container, so constructor injection works.

## 4. Routes

Registered automatically (set `routes.enabled` to `false` to define your own):

| Method and path | Paste into Slimpay as |
|---|---|
| `POST /slimpay/student-lookup` | Student lookup URL |
| `POST /slimpay/invoice-lookup` | Invoice lookup URL |
| `POST /slimpay/webhook` | Webhook URL |

Change the prefix or add middleware in `config/slimpay.php` (`routes.prefix`, `routes.middleware`).

These are server-to-server calls, so exclude them from CSRF.

Laravel 11/12 (`bootstrap/app.php`):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: ['slimpay/*']);
})
```

Laravel 10 (`app/Http/Middleware/VerifyCsrfToken.php`):

```php
protected $except = ['slimpay/*'];
```

## 5. React to payments

In `AppServiceProvider::boot()`:

```php
use Slimpay\School\{WebhookHandler, WebhookEvent};

app(WebhookHandler::class)->on('payment.received', function (WebhookEvent $e) {
    if ($e->isTest()) return;
    dispatch(new MarkFeePaid($e->checkoutReference(), $e->studentId(), $e->amount()));
});
```

Queue the heavy work so the webhook answers within a second or two.

## 6. Collect an invoice

```php
use Slimpay\School\Client;

public function pay(Invoice $invoice, Client $slimpay)
{
    $checkout = $slimpay->collectInvoice(config('slimpay.school_id'), $invoice->number, route('payments.return'));
    return redirect()->away($checkout->url);
}
```

## Behind a proxy or load balancer

Signature verification uses the **raw request body**. Make sure nothing rewrites it before Laravel (some WAFs or middleware that normalise JSON do), and that your server clock is accurate (NTP).

## Config reference

| Key | Default | Purpose |
|---|---|---|
| `student_lookup_secret` | `env(SLIMPAY_STUDENT_LOOKUP_SECRET)` | Verifies student lookups |
| `invoice_lookup_secret` | `env(SLIMPAY_INVOICE_LOOKUP_SECRET)` | Verifies invoice lookups |
| `webhook_secret` | `env(SLIMPAY_WEBHOOK_SECRET)` | Verifies webhooks (optional while unsigned) |
| `student_lookup` / `invoice_lookup` | `null` | Your implementing classes |
| `api_key`, `school_id`, `base_url` | env | `Client` settings |
| `tolerance` | `300` | Allowed clock skew in seconds |
| `routes.enabled` / `prefix` / `middleware` | `true` / `slimpay` / `[]` | Route registration |
