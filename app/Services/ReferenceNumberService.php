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

    public function __construct(private readonly ConnectionInterface $connection) {}

    /**
     * Generate the next reference number for the supplied transaction category.
     *
     * @throws ReferenceNumberException
     */
    public function generateReferenceNumber(string $category): string
    {
        $normalizedCategory = Str::upper(trim($category));

        if (! in_array($normalizedCategory, self::SUPPORTED_CATEGORIES, true)) {
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

    /**
     * Estimate only. Reading a form does not reserve a number.
     *
     * @return array{year: string, month: string, next_number: string}
     */
    public function nextNumberingDefaults(string $category): array
    {
        $now = Carbon::now();

        return [
            'year' => $now->format('Y'),
            'month' => $now->format('m'),
            'next_number' => $this->previewSequence($category, (int) $now->format('Y')),
        ];
    }

    public function previewSequence(string $category, int $year): string
    {
        $category = $this->normalizeCategory($category);
        $stored = (int) $this->connection->table('reference_sequences')
            ->where('category', $category)->where('year', $year)->value('last_sequence');

        return $this->padDocumentSequence($this->nextSequence(max($stored, $this->historicalSequence($category, $year))));
    }

    /**
     * Allocate within the caller's save transaction so rollback also restores the counter.
     */
    public function allocateDocumentReference(
        string $category,
        string $year,
        string $month,
        ?string $fundAbbreviation = null,
        bool $isContinuation = false
    ): string {
        $category = $this->normalizeCategory($category);

        return $this->connection->transaction(function () use ($category, $year, $month, $fundAbbreviation, $isContinuation) {
            $row = $this->lockDocumentSequence($category, (int) $year);
            $sequence = $this->nextSequence(max((int) $row->last_sequence, $this->historicalSequence($category, (int) $year)));
            $this->connection->table('reference_sequences')->where('id', $row->id)
                ->update(['last_sequence' => $sequence, 'updated_at' => now()]);
            $number = $this->padDocumentSequence($sequence);

            return match ($category) {
                'PR' => $this->buildPRReferenceNumber((string) $fundAbbreviation, $year, $month, $number, $isContinuation),
                'PO' => $this->buildPOReferenceNumber($year, $month, $number, $isContinuation),
                'VCH' => "VCH-{$year}-{$month}-{$number}",
            };
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Serialize recognizable manual numbers with the prefix category, even when the
     * owning document differs. External formats do not consume automatic numbers.
     */
    public function trackManualReference(string $category, string $reference): void
    {
        $this->normalizeCategory($category);
        $parsed = $this->parseRecognizedReference($reference);
        if ($parsed === null) {
            return;
        }

        $this->connection->transaction(function () use ($parsed) {
            $row = $this->lockDocumentSequence($parsed['category'], $parsed['year']);
            $this->connection->table('reference_sequences')->where('id', $row->id)->update([
                'last_sequence' => max((int) $row->last_sequence, $parsed['sequence']),
                'updated_at' => now(),
            ]);
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Upsert acquires an exclusive duplicate-key lock before reading the counter.
     * INSERT IGNORE would take shared locks and deadlock when callers upgrade them.
     */
    private function lockDocumentSequence(string $category, int $year): object
    {
        $this->connection->table('reference_sequences')->upsert([[
            'category' => $category,
            'year' => $year,
            'last_sequence' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['category', 'year'], ['category']);

        return $this->connection->table('reference_sequences')
            ->where('category', $category)->where('year', $year)->lockForUpdate()->first();
    }

    private function historicalSequence(string $category, int $year): int
    {
        $maximum = 0;
        // References are globally unique, even when a manual reference uses another
        // category's prefix. Deleted transactions still own their references too.
        foreach ($this->connection->table('transactions')
            ->where('reference_number', 'like', "%-{$year}-%")->pluck('reference_number') as $reference) {
            $parsed = $this->parseDocumentReference($category, $reference);
            if ($parsed !== null && $parsed['year'] === $year) {
                $maximum = max($maximum, $parsed['sequence']);
            }
        }

        return $maximum;
    }

    /** @return array{category: string, year: int, sequence: int}|null */
    private function parseRecognizedReference(string $reference): ?array
    {
        foreach (self::SUPPORTED_CATEGORIES as $category) {
            $parsed = $this->parseDocumentReference($category, $reference);
            if ($parsed !== null) {
                return ['category' => $category] + $parsed;
            }
        }

        return null;
    }

    /** @return array{year: int, sequence: int}|null */
    private function parseDocumentReference(string $category, string $reference): ?array
    {
        $prefix = $category === 'PR' ? 'PR-.+' : $category;
        if (preg_match('/^(?:CONT-)?'.$prefix.'-(\d{4})-(?:0[1-9]|1[0-2])-(\d+)$/iD', $reference, $matches)
            || preg_match('/^'.$category.'-(\d{4})-(\d+)$/iD', $reference, $matches)) {
            // The existing schema stores an unsigned 32-bit sequence. Never wrap it.
            $sequence = ltrim($matches[2], '0') ?: '0';
            if (strlen($sequence) > 10 || (strlen($sequence) === 10 && strcmp($sequence, '4294967295') > 0)) {
                throw new ReferenceNumberException('This reference sequence exceeds the supported range.');
            }

            return ['year' => (int) $matches[1], 'sequence' => (int) $sequence];
        }

        return null;
    }

    private function nextSequence(int $current): int
    {
        if ($current >= 4294967295) {
            throw new ReferenceNumberException('This yearly reference sequence is exhausted.');
        }

        return $current + 1;
    }

    private function padDocumentSequence(int $sequence): string
    {
        return str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    private function normalizeCategory(string $category): string
    {
        $category = Str::upper(trim($category));
        if (! in_array($category, self::SUPPORTED_CATEGORIES, true)) {
            throw new ReferenceNumberException("Unsupported transaction category [{$category}].");
        }

        return $category;
    }

    public function isDuplicateReferenceException(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true)
            && str_contains(strtolower($exception->getMessage()), 'reference_number');
    }

    /**
     * Validate that a reference number is unique across all transactions.
     *
     * @param  string  $referenceNumber  The full formatted reference number to check
     * @return bool True if available (unique), false if already exists
     */
    public function validateUniqueReference(string $referenceNumber): bool
    {
        return ! $this->connection->table('transactions')
            ->where('reference_number', $referenceNumber)
            ->exists();
    }

    /**
     * Build a PR reference number from manual input components.
     *
     * @param  string  $fundTypeAbbr  Fund type abbreviation (e.g., 'GAA', 'SEF')
     * @param  string  $year  4-digit year (e.g., '2025')
     * @param  string  $month  2-digit month (e.g., '10')
     * @param  string  $number  Freeform number (e.g., '001', '9999')
     * @param  bool  $isContinuation  Whether this is a continuation PR
     * @return string Formatted reference number (e.g., 'PR-GAA-2025-10-001' or 'CONT-PR-GAA-2025-10-001')
     */
    public function buildPRReferenceNumber(
        string $fundTypeAbbr,
        string $year,
        string $month,
        string $number,
        bool $isContinuation
    ): string {
        $prefix = $isContinuation ? 'CONT-' : '';

        return sprintf('%sPR-%s-%s-%s-%s', $prefix, $fundTypeAbbr, $year, $month, $number);
    }

    /**
     * Build a PO reference number from manual input components.
     *
     * @param  string  $year  4-digit year (e.g., '2025')
     * @param  string  $month  2-digit month (e.g., '10')
     * @param  string  $number  Freeform number (e.g., '001', '9999')
     * @param  bool  $isContinuation  Whether this is a continuation PO
     * @return string Formatted reference number (e.g., 'PO-2025-10-001' or 'CONT-PO-2025-10-001')
     */
    public function buildPOReferenceNumber(
        string $year,
        string $month,
        string $number,
        bool $isContinuation
    ): string {
        $prefix = $isContinuation ? 'CONT-' : '';

        return sprintf('%sPO-%s-%s-%s', $prefix, $year, $month, $number);
    }
}
