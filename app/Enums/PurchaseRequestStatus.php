<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ProcurementReview = 'procurement_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }
}
