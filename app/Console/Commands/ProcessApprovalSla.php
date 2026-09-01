<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ApprovalTaskLifecycleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('approvals:process-sla')]
#[Description('Notify and escalate overdue approval tasks')]
final class ProcessApprovalSla extends Command
{
    public function handle(ApprovalTaskLifecycleService $tasks): int
    {
        $result = $tasks->processSla();
        $this->info(sprintf(
            'Approval SLA processed: %d warnings, %d expired, %d escalated.',
            $result['warnings'],
            $result['expired'],
            $result['escalated'],
        ));

        return self::SUCCESS;
    }
}
