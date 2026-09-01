<?php

declare(strict_types=1);

namespace App\Enums;

enum SampleShipmentStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ProcurementReview = 'procurement_review';
    case Approved = 'approved';
    case Shipped = 'shipped';
    case Received = 'received';
    case Confirmed = 'confirmed';
    case Returned = 'returned';
    case Stored = 'stored';
    case Complete = 'complete';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
