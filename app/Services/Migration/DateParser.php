<?php

declare(strict_types=1);

namespace App\Services\Migration;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DateParser
{
    private const FORMATS = [
        'Y-m-d',
        'Y-m-d H:i:s',
        'm/d/Y',
        'm/d/Y H:i:s',
        'd/m/Y',
        'd-m-Y',
        'M d, Y',
        'F d, Y',
        'Y/m/d',
        'm-d-Y',
    ];

    public function parse(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        foreach (self::FORMATS as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date;
                }
            } catch (\Exception) {
                continue;
            }
        }

        // Try Carbon's generic parse as a last resort
        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            Log::warning('DateParser: Unparseable date value', ['value' => $value]);
            return null;
        }
    }
}
