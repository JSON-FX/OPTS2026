<?php

declare(strict_types=1);

namespace App\Http\Middleware;

/** Preserve scan bytes while retaining normal trimming for every other input. */
class TrimStrings extends \Illuminate\Foundation\Http\Middleware\TrimStrings
{
    protected function transform($key, $value)
    {
        if ($key === 'scanned_reference' || str_starts_with($key, 'scanned_references.')) {
            return $value;
        }

        return parent::transform($key, $value);
    }
}
