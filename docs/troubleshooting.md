# Troubleshooting and FAQ

## "Signature does not match" (401)

Work through the list:

1. **Wrong secret.** Student lookup, invoice lookup and webhooks each have their own secret. Regenerating invalidates the old one.
2. **Body was modified before verification.** You must pass the raw body. Check any middleware that parses and re-serialises JSON, and don't use `json_encode($request->all())`.
3. **Trailing whitespace / env quoting.** A secret copied with a newline or surrounding quotes won't match. `trim()` and print the length (64 hex chars).
4. **Proxy rewriting.** Some CDNs/WAFs change bodies or strip custom headers. Confirm `X-Slimpay-*` headers reach PHP.

## "Request timestamp is outside the allowed window"

Your server clock is off. Sync with NTP (`timedatectl status`). Don't raise `tolerance` as a fix.

## "Missing or invalid Slimpay signature headers"

- Headers aren't reaching PHP. Under php-fpm, use the `$_SERVER` fallback in [other-frameworks.md](other-frameworks.md#reading-headers-without-getallheaders).
- Someone is calling the endpoint directly (browser, scanner). That is expected and correct to reject.

## Slimpay says my URL "can't be used"

URLs must be **public HTTPS**. `http://`, `localhost`, `127.0.0.1`, private ranges (10.x, 192.168.x) and domains that don't resolve are refused. Use a tunnel for development.

## Invoice collect fails with `INVOICE_NOT_FOUND`

Slimpay couldn't get a valid answer from your invoice lookup: wrong URL, endpoint slower than 8 s, not-found response, or a body missing `invoice.amount` / `student.student_id` / `student.name`. Test the endpoint with the curl recipe in [testing.md](testing.md) and check your logs.

## My webhook handler ran twice

Expected if someone pressed **Resend**. Make the handler idempotent on `checkout_reference` ([webhooks.md](webhooks.md#idempotency)).

## Webhooks aren't arriving

1. Webhook URL saved and public HTTPS?
2. Open the Webhooks page: is there a delivery row? Its status or error tells you whether Slimpay reached you.
3. Is your route behind auth, CSRF or a firewall?
4. Does your endpoint return 2xx quickly (under 5 s)?

## FAQ

**Do I need all three integrations?** No. Webhooks alone are enough to reconcile payments; lookups and `collectInvoice()` are optional.

**Sandbox vs live?** The API key decides for `Client`. Lookups and webhooks use whichever environment the payment happened in.

**Which PHP versions?** 8.1+. Tested on 8.4.

**Can I use Guzzle instead of curl?** Yes, pass a transport callable ([api-client.md](api-client.md#custom-http-transport)).

**Can I verify signatures in another language?** Yes. The algorithm is plain HMAC-SHA256 ([security.md](security.md#verify-it-yourself-non-php-or-manual)).

**Is there a retry policy for webhooks?** No automatic retry. Use Resend after fixing your endpoint, and reconcile via your dashboard if you were down.

**Amounts: naira or kobo?** Naira, as numbers (for example `25000`).
