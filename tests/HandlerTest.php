<?php

namespace Slimpay\School\Tests;

use PHPUnit\Framework\TestCase;
use Slimpay\School\Contracts\InvoiceLookup;
use Slimpay\School\Contracts\StudentLookup;
use Slimpay\School\Exceptions\InvalidSignatureException;
use Slimpay\School\Exceptions\MalformedRequestException;
use Slimpay\School\LookupHandler;
use Slimpay\School\Signature;
use Slimpay\School\WebhookHandler;

class HandlerTest extends TestCase
{
    private const SECRET = 'whsec_test';

    private function signed(string $body, ?int $ts = null, string $secret = self::SECRET): array
    {
        $ts = (string) ($ts ?? time());
        return ['x-slimpay-timestamp' => [$ts], 'X-Slimpay-Signature' => Signature::sign($ts, $body, $secret)];
    }

    private function students(): StudentLookup
    {
        return new class implements StudentLookup {
            public function findStudent(string $id): ?array
            {
                return $id === 'STU-1' ? ['name' => 'Jane Doe', 'class' => 'SS3'] : null;
            }
        };
    }

    public function testStudentFoundAndNotFound(): void
    {
        $h = new LookupHandler(self::SECRET, $this->students());
        $body = json_encode(['school_id' => 'SCH-1', 'student_id' => 'STU-1']);
        $this->assertSame(['found' => true, 'student' => ['name' => 'Jane Doe', 'class' => 'SS3']], $h->handleStudent($body, $this->signed($body)));

        $body = json_encode(['school_id' => 'SCH-1', 'student_id' => 'nope']);
        $this->assertSame(['found' => false], $h->handleStudent($body, $this->signed($body)));
    }

    public function testRejectsBadSignature(): void
    {
        $h = new LookupHandler(self::SECRET, $this->students());
        $body = json_encode(['student_id' => 'STU-1']);
        $this->expectException(InvalidSignatureException::class);
        $h->handleStudent($body, $this->signed($body, null, 'wrong'));
    }

    public function testRejectsTamperedBodyAndStaleTimestamp(): void
    {
        $h = new LookupHandler(self::SECRET, $this->students());
        $body = json_encode(['student_id' => 'STU-1']);
        $headers = $this->signed($body);

        try {
            $h->handleStudent(json_encode(['student_id' => 'STU-2']), $headers);
            $this->fail('tampered body accepted');
        } catch (InvalidSignatureException) {
        }

        $this->expectException(InvalidSignatureException::class);
        $h->handleStudent($body, $this->signed($body, time() - 3600));
    }

    public function testMalformedBody(): void
    {
        $h = new LookupHandler(self::SECRET, $this->students());
        $body = json_encode(['school_id' => 'x']);
        $this->expectException(MalformedRequestException::class);
        $h->handleStudent($body, $this->signed($body));
    }

    public function testInvoiceValidatesShape(): void
    {
        $invoices = new class implements InvoiceLookup {
            public function findInvoice(string $id): ?array
            {
                return $id === 'INV-1'
                    ? ['invoice' => ['amount' => 45000, 'invoice_id' => 'INV-1'], 'student' => ['student_id' => 'STU-1', 'name' => 'Jane']]
                    : ['invoice' => ['description' => 'no amount'], 'student' => []];
            }
        };
        $h = new LookupHandler(self::SECRET, null, $invoices);

        $body = json_encode(['invoice_id' => 'INV-1']);
        $this->assertTrue($h->handleInvoice($body, $this->signed($body))['found']);

        $body = json_encode(['invoice_id' => 'INV-2']);
        $this->expectException(\UnexpectedValueException::class);
        $h->handleInvoice($body, $this->signed($body));
    }

    public function testWebhookListenersAndOptionalSignature(): void
    {
        $seen = [];
        $h = (new WebhookHandler())->on('payment.received', function ($e) use (&$seen) { $seen[] = $e->amount(); });
        $event = $h->handle(json_encode(['event' => 'payment.received', 'amount' => 25000, 'checkout_reference' => 'SCH-TESTAB']), []);
        $this->assertSame([25000.0], $seen);
        $this->assertTrue($event->isTest());

        $signedHandler = new WebhookHandler(self::SECRET);
        $body = json_encode(['event' => 'payment.received']);
        $this->assertTrue($signedHandler->parse($body, $this->signed($body))->isPaymentReceived());
        $this->expectException(InvalidSignatureException::class);
        $signedHandler->parse($body, []);
    }
}
