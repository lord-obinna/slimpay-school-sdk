<?php

namespace Slimpay\School;

use Slimpay\School\Contracts\InvoiceLookup;
use Slimpay\School\Contracts\StudentLookup;
use Slimpay\School\Exceptions\InvalidSignatureException;
use Slimpay\School\Exceptions\MalformedRequestException;

/**
 * Verifies Slimpay's signed lookup requests and builds the response body.
 * Framework-agnostic: pass the raw body and headers, send back the array as JSON.
 */
final class LookupHandler
{
    public function __construct(
        private string $secret,
        private ?StudentLookup $students = null,
        private ?InvoiceLookup $invoices = null,
        private int $tolerance = Signature::DEFAULT_TOLERANCE,
    ) {
    }

    /**
     * @param array<string, string|string[]> $headers
     * @return array{found: bool, student?: array<string, mixed>}
     * @throws InvalidSignatureException|MalformedRequestException
     */
    public function handleStudent(string $rawBody, array $headers): array
    {
        $data = $this->parse($rawBody, $headers, 'student_id');

        if ($this->students === null) {
            throw new MalformedRequestException('Student lookup is not configured.');
        }

        $student = $this->students->findStudent($data['student_id']);

        return $student === null ? ['found' => false] : ['found' => true, 'student' => $student];
    }

    /**
     * @param array<string, string|string[]> $headers
     * @return array{found: bool, invoice?: array<string, mixed>, student?: array<string, mixed>}
     * @throws InvalidSignatureException|MalformedRequestException
     */
    public function handleInvoice(string $rawBody, array $headers): array
    {
        $data = $this->parse($rawBody, $headers, 'invoice_id');

        if ($this->invoices === null) {
            throw new MalformedRequestException('Invoice lookup is not configured.');
        }

        $result = $this->invoices->findInvoice($data['invoice_id']);

        if ($result === null) {
            return ['found' => false];
        }

        $invoice = $result['invoice'] ?? null;
        $student = $result['student'] ?? null;

        // Slimpay rejects responses missing these, so fail loudly in the developer's code instead.
        if (!is_array($invoice) || !isset($invoice['amount']) || !is_array($student)
            || empty($student['student_id']) || empty($student['name'])) {
            throw new \UnexpectedValueException(
                'findInvoice() must return invoice.amount and student.student_id + student.name.'
            );
        }

        return ['found' => true, 'invoice' => $invoice, 'student' => $student];
    }

    /** @return array<string, mixed> */
    private function parse(string $rawBody, array $headers, string $idKey): array
    {
        Signature::verify($rawBody, $headers, $this->secret, $this->tolerance);

        $data = json_decode($rawBody, true);

        if (!is_array($data) || !isset($data[$idKey]) || !is_string($data[$idKey]) || $data[$idKey] === '') {
            throw new MalformedRequestException("Request body must contain a non-empty \"{$idKey}\".");
        }

        return $data;
    }
}
