<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ApprovalTaskLifecycleService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessApprovalSlaJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function uniqueId(): string
    {
        return 'approval-sla';
    }

    public function handle(ApprovalTaskLifecycleService $tasks): void
    {
        $tasks->processSla();
    }
}
