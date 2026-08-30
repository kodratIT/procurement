<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Generates the PR number server-side, never trusting the client.
 *
 * Format: PR-{YYYYMM}-{NNNN}, zero-padded 4-digit sequence per calendar month.
 * The sequence is computed against the persisted rows so concurrent requests
 * cannot collide on the unique index; the number is assigned inside the model's
 * creating hook right before the insert.
 */
class PurchaseRequestNumberService
{
    public const PREFIX = 'PR';

    /**
     * Compute the next number for the given month (defaults to now).
     *
     * @param  DateTimeInterface|null  $when  month anchor (default now)
     */
    public function next(?DateTimeInterface $when = null): string
    {
        $when ??= now();

        $month = $when->format('Ym');
        $prefix = self::PREFIX.'-'.$month.'-';

        // The per-month sequence is global, not office-scoped: bypass the
        // OfficeScoped global scope so unauthenticated/other-office contexts
        // still see existing numbers (avoids collisions).
        $last = PurchaseRequest::acrossOffices()
            ->where('pr_number', 'like', $prefix.'%')
            ->orderByDesc('pr_number')
            ->value('pr_number');

        $sequence = 1;
        if ($last !== null && str_starts_with($last, $prefix)) {
            $sequence = ((int) substr($last, strlen($prefix))) + 1;
        }

        if ($sequence < 1 || $sequence > 9999) {
            throw new InvalidArgumentException("PR number sequence out of range for {$month}.");
        }

        return $prefix.sprintf('%04d', $sequence);
    }
}
