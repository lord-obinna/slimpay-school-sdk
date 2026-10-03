<?php

return [
    // Secrets shown once in the Slimpay school portal (Settings → Student / Invoice lookup).
    'student_lookup_secret' => env('SLIMPAY_STUDENT_LOOKUP_SECRET'),
    'invoice_lookup_secret' => env('SLIMPAY_INVOICE_LOOKUP_SECRET'),

    // Optional while Slimpay webhooks are unsigned; when set, signatures are enforced.
    'webhook_secret' => env('SLIMPAY_WEBHOOK_SECRET'),

    // Classes implementing Slimpay\School\Contracts\StudentLookup / InvoiceLookup.
    'student_lookup' => null,
    'invoice_lookup' => null,

    // For the API client: your school API key (sandbox or live) and school id, e.g. SCH-9K2H7XQZ.
    'api_key'  => env('SLIMPAY_API_KEY'),
    'school_id' => env('SLIMPAY_SCHOOL_ID'),
    'base_url' => env('SLIMPAY_BASE_URL', 'https://api.slimpay.ng/api/v1'),

    // Max clock skew (seconds) allowed on the signed timestamp.
    'tolerance' => 300,

    // Register POST {prefix}/student-lookup, /invoice-lookup and /webhook automatically.
    'routes' => [
        'enabled'    => true,
        'prefix'     => 'slimpay',
        'middleware' => [],
    ],
];
