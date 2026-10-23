# Getting started

This checklist takes a school developer from zero to a working integration. Do the steps in order; each one can be tested on its own.

## Requirements

- PHP 8.1 or newer, with `ext-json`
- `ext-curl` if you use `Client` (or pass your own HTTP transport)
- A **public HTTPS URL** for your endpoints. Slimpay refuses to call loopback, private or non-HTTPS addresses. For local development use a tunnel such as ngrok or Cloudflare Tunnel.
- An approved school account on Slimpay

## 1. Install

```bash
composer require hilinks/slimpay-school-sdk
```

## 2. Decide which pieces you need

| Goal | Required pieces |
|---|---|
| Parents pay on Slimpay's hosted checkout and you want to verify the student ID they type | Student lookup |
| Your portal creates invoices and sends parents to pay | Invoice lookup + `Client::collectInvoice()` |
| You need to mark fees paid in your own database | Webhooks (almost always) |

You can adopt them one at a time.

## 3. Create secrets in the Slimpay portal

- **Settings → Student Lookup**: enter your URL, click *Generate secret*, copy it.
- **Settings → Invoice Collection**: same.
- **Webhooks → Signing secret**: click *Generate secret*, copy it.
- **Settings → API Keys**: copy your sandbox key while testing.

Put them in your environment:

```env
SLIMPAY_STUDENT_LOOKUP_SECRET=...
SLIMPAY_INVOICE_LOOKUP_SECRET=...
SLIMPAY_WEBHOOK_SECRET=...
SLIMPAY_API_KEY=...
SLIMPAY_SCHOOL_ID=SCH-XXXXXXXX
```

> A secret is displayed once. If you lose it, regenerate it. The old one stops working immediately, so update your environment straight away.

## 4. Implement your endpoints

Pick your stack:

- Laravel: [docs/laravel.md](laravel.md) (routes and controller are provided)
- Anything else: [docs/other-frameworks.md](other-frameworks.md)

Then implement `StudentLookup` / `InvoiceLookup` ([docs/lookups.md](lookups.md)) and register listeners ([docs/webhooks.md](webhooks.md)).

## 5. Register your URLs in Slimpay

Paste your public URLs into the portal fields (Student lookup URL, Invoice lookup URL, Webhook URL), save, and enable lookups.

## 6. Test

1. Webhooks page → **Send Test Webhook**. Your listener fires with a test event (`$event->isTest() === true`).
2. Settings → **Test Connection** for each lookup.
3. Make a small sandbox payment end to end.

See [docs/testing.md](testing.md) for local signing helpers and unit-test examples.

## 7. Go live

- Swap the sandbox API key for the live key.
- Confirm your webhook handler is idempotent ([docs/webhooks.md](webhooks.md#idempotency)).
- Confirm secrets are in your production environment, not in code.
- Make one real low-value payment and check that your system marks it paid.
