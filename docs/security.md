# Security

## How signing works

For lookups (and webhooks when a secret is set), Slimpay sends:

```
X-Slimpay-Timestamp: <unix seconds>
X-Slimpay-Signature: hex( HMAC-SHA256( secret, "<timestamp>.<raw request body>" ) )
```

The SDK:

1. Rejects requests missing either header.
2. Rejects timestamps more than `tolerance` seconds (default 300) from your clock. The timestamp is inside the signed string, so an attacker can't refresh an old request.
3. Recomputes the HMAC over the **raw bytes** and compares with `hash_equals` (constant time).

Result: only someone holding the secret can produce a request your endpoint accepts, and a captured request is useless after five minutes.

## Verify it yourself (non-PHP or manual)

```php
$expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
$valid = hash_equals($expected, $signatureHeader);
```

## Do

- Keep secrets and the API key in environment variables or a secret manager.
- Always verify before parsing or acting on a body.
- Serve endpoints over HTTPS.
- Keep your server clock synced (NTP).
- Make webhook handlers idempotent ([webhooks.md](webhooks.md#idempotency)).
- Regenerate a secret immediately if it leaks (the old one dies instantly).

## Don't

- Don't commit secrets or log full signature headers/secrets.
- Don't verify against `json_encode($decoded)`; use the raw body.
- Don't expose the live API key to browsers or mobile apps.
- Don't mark an invoice paid from the payer's redirect back to your site.
- Don't disable verification "temporarily" in production.

## Webhook caveat

Webhooks are only signed once you generate a webhook secret. Until then they arrive unsigned, so confirm anything valuable before acting. Generate the secret as soon as you can.

## Secret rotation

1. Generate a new secret in the Slimpay portal. The old one stops immediately.
2. Update your environment and reload workers.
3. Use **Send Test Webhook** or **Test Connection** to confirm.

Expect a short window of rejected requests; plan rotation for a quiet time.

## Reporting vulnerabilities

Email security issues privately to the Slimpay team rather than opening public issues.
