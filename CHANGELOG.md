# Changelog

## 1.0.0

- `Signature`: sign and verify Slimpay requests (HMAC-SHA256, timestamp replay window).
- `LookupHandler`: student and invoice lookup request handling.
- `WebhookHandler` / `WebhookEvent`: webhook parsing, optional signature verification, listeners.
- `Client::collectInvoice()` for school-portal-initiated collection.
- Laravel service provider, routes, controller and config.
