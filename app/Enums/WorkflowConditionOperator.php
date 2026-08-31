<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkflowConditionOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case In = 'in';
    case GreaterThanOrEqual = 'gte';
    case LessThanOrEqual = 'lte';
    case Between = 'between';
}
