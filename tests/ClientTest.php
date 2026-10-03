<?php

namespace Slimpay\School\Tests;

use PHPUnit\Framework\TestCase;
use Slimpay\School\Client;
use Slimpay\School\Exceptions\ApiException;

class ClientTest extends TestCase
{
    public function testCollectInvoiceSendsKeyAndParsesCheckout(): void
    {
        $seen = null;
        $client = new Client('key_123', 'https://api.test/api/v1/', function ($m, $url, $headers, $body) use (&$seen) {
            $seen = compact('m', 'url', 'headers', 'body');
            return [200, json_encode(['status' => true, 'data' => [
                'checkout_reference' => 'SCH-ABC', 'checkout_url' => 'https://pay.test/x', 'invoice_id' => 'INV-1',
                'amount' => 45000, 'split' => ['school_receives' => 44325, 'platform_fee' => 675],
                'student' => ['student_id' => 'STU-1', 'name' => 'Jane'], 'expires_at' => '2026-10-02T12:00:00+00:00',
            ]])];
        });

        $c = $client->collectInvoice('SCH-1', 'INV-1', 'https://school.test/return');

        $this->assertSame('https://api.test/api/v1/invoices/collect', $seen['url']);
        $this->assertSame('key_123', $seen['headers']['X-School-Key']);
        $this->assertSame(['school_id' => 'SCH-1', 'invoice_id' => 'INV-1', 'redirect_url' => 'https://school.test/return'], json_decode($seen['body'], true));
        $this->assertSame('SCH-ABC', $c->reference);
        $this->assertSame(44325.0, $c->schoolReceives);
        $this->assertSame('https://pay.test/x', $c->url);
    }

    public function testErrorsBecomeApiException(): void
    {
        $client = new Client('k', Client::DEFAULT_BASE_URL, fn () => [404, json_encode(['status' => false, 'message' => 'nope', 'code' => 'INVOICE_NOT_FOUND'])]);

        try {
            $client->collectInvoice('S', 'I', 'https://x.test');
            $this->fail('expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(404, $e->httpStatus);
            $this->assertSame('INVOICE_NOT_FOUND', $e->errorCode);
        }
    }
}
