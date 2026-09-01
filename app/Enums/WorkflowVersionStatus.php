<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkflowVersionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';

    public static function options(): array
    {
        return [
            self::Draft->value => 'Draft',
            self::Active->value => 'Aktif',
            self::Retired->value => 'Pensiun',
        ];
    }
}
