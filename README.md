# Slimpay School SDK

[![PHP](https://img.shields.io/badge/php-%5E8.1-777bb4)](https://www.php.net) [![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Connect your school's system (student portal, fee system, ERP) to [Slimpay](https://slimpay.ng) without hand-writing signature checks and payload parsing.

| You want to… | Use | Guide |
|---|---|---|
| Confirm a student ID while a parent pays | `LookupHandler::handleStudent()` | [Lookups](docs/lookups.md) |
| Let Slimpay fetch an invoice's amount and student | `LookupHandler::handleInvoice()` | [Lookups](docs/lookups.md) |
| Get notified when a payment lands | `WebhookHandler` | [Webhooks](docs/webhooks.md) |
| Start a payment for an invoice from your own portal | `Client::collectInvoice()` | [API client](docs/api-client.md) |
| Plug all of this into Laravel in minutes | `SlimpayServiceProvider` | [Laravel](docs/laravel.md) |

The core is plain PHP 8.1+ with **no runtime dependencies**, so it works in Laravel, Symfony, CodeIgniter, Slim or a single `index.php`.

## Install

```bash
composer require slimpay/school-sdk
```

## 60-second tour

```php
use Slimpay\School\{Client, LookupHandler, WebhookHandler, WebhookEvent};
use Slimpay\School\Contracts\{StudentLookup, InvoiceLookup};

// 1. Slimpay asks YOUR system about a student (signed request → you answer).
$lookups = new LookupHandler($studentSecret, new MyStudents());
echo json_encode($lookups->handleStudent(file_get_contents('php://input'), getallheaders()));

// 2. Slimpay tells YOU a payment arrived.
(new WebhookHandler($webhookSecret))
    ->on('payment.received', fn (WebhookEvent $e) => markPaid($e->studentId(), $e->amount()))
    ->handle(file_get_contents('php://input'), getallheaders());

// 3. YOU ask Slimpay to collect an invoice.
$checkout = (new Client($apiKey))->collectInvoice('SCH-9K2H7XQZ', 'INV-8841', 'https://portal.example.edu.ng/return');
header('Location: ' . $checkout->url);
```

## The big picture

```
        ┌───────────────┐  3. collectInvoice()   ┌──────────┐
        │  Your portal  │ ─────────────────────▶ │          │
        │ (school sys.) │ ◀───────────────────── │ Slimpay  │
        │               │   checkout URL         │          │
        │               │ ◀───── 1/2. signed ─── │          │
        │               │   lookups (you reply)  │          │
        │               │ ◀───── 4. webhook ──── │          │
        └───────────────┘   payment.received     └──────────┘
```

Three secrets are involved, all issued in the Slimpay school portal:

| Secret | Where to get it | Used for |
|---|---|---|
| Student lookup secret | Settings → Student Lookup | Verifying student lookup requests |
| Invoice lookup secret | Settings → Invoice Collection | Verifying invoice lookup requests |
| Webhook secret | Webhooks → Signing secret | Verifying payment webhooks |
| API key (sandbox / live) | Settings → API Keys | Calling Slimpay (`Client`) |

Secrets are shown **once**. Store them in environment variables, never in git.

## Documentation

1. [Getting started](docs/getting-started.md): the end-to-end setup checklist
2. [Lookups](docs/lookups.md): student and invoice lookup endpoints
3. [Webhooks](docs/webhooks.md): payload reference, idempotency, retries
4. [API client](docs/api-client.md): `collectInvoice()` and error codes
5. [Laravel](docs/laravel.md): zero-boilerplate integration
6. [Other frameworks](docs/other-frameworks.md): plain PHP, Symfony, Slim
7. [Security](docs/security.md): how signing works and what to protect
8. [Testing](docs/testing.md): sign requests locally, unit-test your handlers
9. [Troubleshooting & FAQ](docs/troubleshooting.md)
10. [Changelog](CHANGELOG.md)

## License

MIT, see [LICENSE](LICENSE).
