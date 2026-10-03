<?php

namespace Slimpay\School\Contracts;

interface InvoiceLookup
{
    /**
     * @return array{
     *   invoice: array{amount: int|float, invoice_id?: string, description?: string, status?: string},
     *   student: array{student_id: string, name: string, class?: string, email?: string, phone?: string}
     * }|null  null if the invoice doesn't exist.
     */
    public function findInvoice(string $invoiceId): ?array;
}
