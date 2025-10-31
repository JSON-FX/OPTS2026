<?php

namespace App\Services;

use App\Exceptions\ReferenceNumberException;
use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

class ReferenceNumberService
{
    private const SUPPORTED_CATEGORIES = ['PR', 'PO', 'VCH'];
    private const BASE_PADDING = 6;
    private const TRANSACTION_ATTEMPTS = 5;

    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    /**
     * Generate the next reference number for the supplied transaction category.
     *
     * @throws ReferenceNumberException
     */
    public function generateReferenceNumber(string $category): string
    {
        $normalizedCategory = Str::upper(trim($category));

        if (!in_array($normalizedCategory, self::SUPPORTED_CATEGORIES, true)) {
            throw new ReferenceNumberException("Unsupported transaction category [{$category}].");
        }

        $now = Carbon::now();
        $year = (int) $now->format('Y');

        try {
            return $this->connection->transaction(
                function () use ($normalizedCategory, $year, $now) {
                    $sequenceRow = $this->connection->table('reference_sequences')
                        ->where('category', $normalizedCategory)
                        ->where('year', $year)
                        ->lockForUpdate()
                        ->first();

                    if ($sequenceRow === null) {
                        $sequence = 1;

                        $this->connection->table('reference_sequences')->insert([
                            'category' => $normalizedCategory,
                            'year' => $year,
                            'last_sequence' => $sequence,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    } else {
                        $sequence = ((int) $sequenceRow->last_sequence) + 1;

                        $this->connection->table('reference_sequences')
                            ->where('id', $sequenceRow->id)
                            ->update([
                                'last_sequence' => $sequence,
                                'updated_at' => $now,
                            ]);
                    }

                    $paddingLength = max(self::BASE_PADDING, strlen((string) $sequence));
                    $paddedSequence = str_pad((string) $sequence, $paddingLength, '0', STR_PAD_LEFT);

                    return sprintf('%s-%d-%s', $normalizedCategory, $year, $paddedSequence);
                },
                self::TRANSACTION_ATTEMPTS
            );
        } catch (QueryException $exception) {
            throw new ReferenceNumberException(
                'Unable to generate reference number due to database contention.',
                0,
                $exception
            );
        } catch (Throwable $exception) {
            throw new ReferenceNumberException(
                'Unexpected error generating reference number.',
                0,
                $exception
            );
        }
    }
}

