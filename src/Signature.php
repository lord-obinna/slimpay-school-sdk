<?php

namespace Slimpay\School;

use Slimpay\School\Exceptions\InvalidSignatureException;

/**
 * Slimpay's request signature: hex(hmac_sha256(secret, "{timestamp}.{rawBody}")),
 * sent as X-Slimpay-Timestamp and X-Slimpay-Signature.
 */
final class Signature
{
    public const HEADER_TIMESTAMP = 'X-Slimpay-Timestamp';
    public const HEADER_SIGNATURE = 'X-Slimpay-Signature';
    public const DEFAULT_TOLERANCE = 300;

    public static function sign(string $timestamp, string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    }

    /**
     * @param array<string, string|string[]> $headers Any casing; first value wins.
     * @throws InvalidSignatureException
     */
    public static function verify(
        string $rawBody,
        array $headers,
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null
    ): void {
        if ($secret === '') {
            throw new InvalidSignatureException('No signing secret configured.');
        }

        $timestamp = self::header($headers, self::HEADER_TIMESTAMP);
        $signature = self::header($headers, self::HEADER_SIGNATURE);

        if ($timestamp === null || $signature === null || !ctype_digit($timestamp)) {
            throw new InvalidSignatureException('Missing or invalid Slimpay signature headers.');
        }

        // Reject replays: the timestamp is covered by the signature, so it can't be forged.
        if ($tolerance > 0 && abs(($now ?? time()) - (int) $timestamp) > $tolerance) {
            throw new InvalidSignatureException('Request timestamp is outside the allowed window.');
        }

        if (!hash_equals(self::sign($timestamp, $rawBody, $secret), strtolower($signature))) {
            throw new InvalidSignatureException('Signature does not match.');
        }
    }

    /** @param array<string, string|string[]> $headers */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                $value = is_array($value) ? ($value[0] ?? null) : $value;
                return $value === null ? null : trim((string) $value);
            }
        }
        return null;
    }
}
