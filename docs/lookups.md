# Lookups

Slimpay calls **your** server in two situations:

| Lookup | When | Purpose |
|---|---|---|
| **Student lookup** | A parent types a student ID on Slimpay's checkout | Confirm the student exists and attach their details to the payment |
| **Invoice lookup** | Your portal calls `collectInvoice()` | Tell Slimpay the invoice amount and which student it belongs to |

Both use the same signed-request mechanism and the same `LookupHandler` class. Each has its own secret, so each gets its own handler instance.

## The request Slimpay sends

```http
POST /your/endpoint HTTP/1.1
Content-Type: application/json
X-Slimpay-Timestamp: 1790000000
X-Slimpay-Signature: 9f2c…(hex hmac-sha256)

{"school_id":"SCH-9K2H7XQZ","student_id":"STU-2201"}
```

Invoice lookups send `{"school_id": "...", "invoice_id": "INV-8841"}`.

`LookupHandler` verifies the signature, checks the timestamp is recent, parses the body and calls your code. You never handle crypto yourself.

## Student lookup

Implement the interface:

```php
use Slimpay\School\Contracts\StudentLookup;

final class MyStudents implements StudentLookup
{
    public function findStudent(string $studentId): ?array
    {
        $s = Student::where('admission_no', $studentId)->first();

        if (!$s) {
            return null;                       // → {"found": false}
        }

        return [
            'name'  => $s->full_name,          // shown to the payer
            'class' => $s->class_name,
            // add anything else you want stored on the payment
            'guardian_phone' => $s->guardian_phone,
        ];
    }
}
```

Respond with what the handler returns:

```php
$handler = new LookupHandler($secret, students: new MyStudents());
$body = $handler->handleStudent($rawBody, $headers);   // ['found' => true, 'student' => [...]]
```

- **Return `null` for unknown students.** Do not throw.
- Extra keys in the returned array are stored verbatim against the payment and forwarded in the webhook as `verified_student_data`.
- Student lookup is best-effort: if your endpoint is down or slow (5 s timeout) the payer can still pay with manually typed details. It never blocks a payment.

## Invoice lookup

```php
use Slimpay\School\Contracts\InvoiceLookup;

final class MyInvoices implements InvoiceLookup
{
    public function findInvoice(string $invoiceId): ?array
    {
        $inv = Invoice::with('student')->where('number', $invoiceId)->first();
        if (!$inv) {
            return null;
        }

        return [
            'invoice' => [
                'invoice_id'  => $inv->number,
                'amount'      => $inv->balance,          // naira, required
                'description' => 'Third Term Tuition',
                'status'      => $inv->isPaid() ? 'paid' : 'unpaid',
            ],
            'student' => [
                'student_id' => $inv->student->admission_no,   // required
                'name'       => $inv->student->full_name,      // required
                'class'      => $inv->student->class_name,
                'email'      => $inv->student->guardian_email,
                'phone'      => $inv->student->guardian_phone,
            ],
        ];
    }
}
```

Rules:

- `invoice.amount`, `student.student_id` and `student.name` are **required**. If one is missing the SDK throws `UnexpectedValueException` so you catch the bug in development (Slimpay would otherwise reject the response).
- `status` must be `unpaid`, `pending` or `due` for the invoice to be collectible. Anything else (for example `paid`) makes `collectInvoice()` fail with `INVOICE_NOT_COLLECTIBLE`.
- Amount must be at least ₦100.
- The timeout is 8 seconds. A failed invoice lookup is a hard failure (there is no manual fallback), so keep this endpoint fast.

## Exceptions

| Exception | Meaning | Respond with |
|---|---|---|
| `InvalidSignatureException` | Not from Slimpay, wrong secret, or timestamp too old | `401` |
| `MalformedRequestException` | Body isn't the expected JSON, or that lookup isn't configured | `400` |
| `UnexpectedValueException` | **Your** `findInvoice()` returned an incomplete array | fix your code |

Do not return details of signature failures beyond the status code.

## Complete plain-PHP endpoint

```php
<?php // student-lookup.php
require __DIR__ . '/vendor/autoload.php';

use Slimpay\School\LookupHandler;
use Slimpay\School\Exceptions\{InvalidSignatureException, MalformedRequestException};

$handler = new LookupHandler(getenv('SLIMPAY_STUDENT_LOOKUP_SECRET'), new MyStudents());

header('Content-Type: application/json');
try {
    echo json_encode($handler->handleStudent(file_get_contents('php://input'), getallheaders()));
} catch (InvalidSignatureException) {
    http_response_code(401); echo json_encode(['error' => 'unauthorized']);
} catch (MalformedRequestException) {
    http_response_code(400); echo json_encode(['error' => 'bad request']);
}
```

> `getallheaders()` is not available under every SAPI (for example php-fpm without Apache helpers). Use the fallback in [other-frameworks.md](other-frameworks.md#reading-headers-without-getallheaders).

## Tuning

`LookupHandler` takes a fourth constructor argument, `tolerance` (seconds, default 300), the maximum clock skew allowed between Slimpay and your server. Keep your server clock synced with NTP rather than raising it.
