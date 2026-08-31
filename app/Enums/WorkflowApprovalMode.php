<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkflowApprovalMode: string
{
    case Sequential = 'sequential';
    case ParallelAll = 'parallel_all';
    case ParallelAny = 'parallel_any';

    public static function options(): array
    {
        return [
            self::Sequential->value => 'Sequential',
            self::ParallelAll->value => 'Parallel (semua)',
            self::ParallelAny->value => 'Parallel (salah satu)',
        ];
    }
}
