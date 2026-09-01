<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Generates PR-YYYYMM-NNNN numbers using an atomic per-month sequence. */
class PurchaseRequestNumberService
{
    public const PREFIX = 'PR';

    public function next(?DateTimeInterface $when = null): string
    {
        $when ??= now();
        $month = $when->format('Ym');
        $prefix = self::PREFIX.'-'.$month.'-';

        // The single-row sequence update is atomic and the unique month key
        // makes concurrent first use safe. Retries handle transient database
        // lock contention (notably SQLite's coarse write lock).
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $sequence = DB::transaction(function () use ($month): int {
                    DB::table('purchase_request_number_sequences')->insertOrIgnore([
                        'month' => $month,
                        'next_sequence' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Lock the existing row before reading it. insertOrIgnore
                    // only guarantees that the row exists; without this lock,
                    // concurrent transactions can read the same value and
                    // overwrite each other's increment.
                    $sequence = (int) DB::table('purchase_request_number_sequences')
                        ->where('month', $month)
                        ->lockForUpdate()
                        ->value('next_sequence');

                    if ($sequence < 1 || $sequence > 9999) {
                        throw new InvalidArgumentException("PR number sequence out of range for {$month}.");
                    }

                    DB::table('purchase_request_number_sequences')
                        ->where('month', $month)
                        ->update([
                            'next_sequence' => $sequence + 1,
                            'updated_at' => now(),
                        ]);

                    return $sequence;
                }, 5);

                return $prefix.sprintf('%04d', $sequence);
            } catch (QueryException $exception) {
                if ($attempt === 4) {
                    throw $exception;
                }

                usleep(10_000 * ($attempt + 1));
            }
        }

        throw new InvalidArgumentException("Unable to allocate PR number for {$month}.");
    }
}
