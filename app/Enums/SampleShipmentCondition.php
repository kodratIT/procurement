<?php

declare(strict_types=1);

namespace App\Enums;

enum SampleShipmentCondition: string
{
    case New = 'new';
    case Good = 'good';
    case Fair = 'fair';
    case Damaged = 'damaged';
    case Lost = 'lost';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $condition): string => $condition->value, self::cases());
    }
}
