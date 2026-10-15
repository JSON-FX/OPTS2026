<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Match the decoded paper reference, allowing only the scanner's Enter suffix. */
class ScannedDocumentReference implements ValidationRule
{
    public function __construct(private readonly string $reference) {}

    public static function normalize(string $value): string
    {
        return rtrim($value, "\r\n");
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::normalize($value) !== $this->reference) {
            $fail('The scanned reference does not match this document. Scan its QR code again.');
        }
    }
}
