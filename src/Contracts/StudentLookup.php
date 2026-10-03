<?php

namespace Slimpay\School\Contracts;

interface StudentLookup
{
    /**
     * @return array<string, mixed>|null  e.g. ['name' => 'Jane Doe', 'class' => 'SS3'], or null if unknown.
     *                                    Extra keys are stored by Slimpay against the payment.
     */
    public function findStudent(string $studentId): ?array;
}
