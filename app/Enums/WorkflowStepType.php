<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkflowStepType: string
{
    case Review = 'review';
    case Approval = 'approval';
    case Informational = 'informational';
    case Conditional = 'conditional';
    case FinalApproval = 'final_approval';

    public static function options(): array
    {
        return array_combine(
            array_map(static fn (self $type): string => $type->value, self::cases()),
            array_map(static fn (self $type): string => str_replace('_', ' ', ucfirst($type->value)), self::cases()),
        );
    }
}
