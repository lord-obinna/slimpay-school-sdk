<?php

namespace Slimpay\School;

use Slimpay\School\Exceptions\ApiException;

/**
 * Calls Slimpay with your school API key (sandbox or live — the key decides).
 * Uses ext-curl by default; pass $transport to use your own HTTP client.
 */
final class Client
{
    public const DEFAULT_BASE_URL = 'https://api.slimpay.ng/api/v1';

    /** @var callable(string $method, string $url, array<string,string> $headers, ?string $body): array{0:int,1:string} */
    private $transport;

    /** @param ?callable $transport Returns [httpStatus, rawBody]. */
    public function __construct(
        private string $apiKey,
        private string $baseUrl = self::DEFAULT_BASE_URL,
        ?callable $transport = null,
        private int $timeout = 30,
    ) {
        $this->transport = $transport ?? $this->curlTransport(...);
    }

    /**
     * Start a payment for an invoice in your own system. Slimpay calls your invoice lookup
     * endpoint to get the amount and student, then returns a checkout to send the payer to.
     * Safe to retry: the same unexpired checkout is returned for the same invoice.
     *
     * @throws ApiException  e.g. code INVOICE_NOT_FOUND, SETTLEMENT_PENDING, INVOICE_LOOKUP_NOT_CONFIGURED
     */
    public function collectInvoice(string $schoolId, string $invoiceId, string $redirectUrl): Checkout
    {
        $res = $this->post('/invoices/collect', [
            'school_id'    => $schoolId,
            'invoice_id'   => $invoiceId,
            'redirect_url' => $redirectUrl,
        ]);

        return Checkout::fromArray($res['data'] ?? []);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        [$status, $raw] = ($this->transport)(
            'POST',
            rtrim($this->baseUrl, '/') . $path,
            ['X-School-Key' => $this->apiKey, 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
            json_encode($payload),
        );

        $body = json_decode($raw, true);
        $body = is_array($body) ? $body : [];

        if ($status < 200 || $status >= 300 || ($body['status'] ?? true) === false) {
            $message = $body['message'] ?? ($body === [] ? "Unexpected response from Slimpay (HTTP {$status})." : 'Request failed.');
            throw new ApiException($message, $status, $body['code'] ?? null, $body);
        }

        return $body;
    }

    /** @return array{0:int,1:string} */
    private function curlTransport(string $method, string $url, array $headers, ?string $body): array
    {
        if (!function_exists('curl_init')) {
            throw new ApiException('ext-curl is required, or pass a custom $transport to Client.');
        }

        $h = [];
        foreach ($headers as $k => $v) {
            $h[] = "{$k}: {$v}";
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $h,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
        ]);
        $raw = curl_exec($ch);

        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new ApiException("Could not reach Slimpay: {$err}");
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [$status, (string) $raw];
    }
}
