<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ApprovalInstanceStep;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class ApprovalTaskSlaWarning extends Notification
{
    use Queueable;

    public function __construct(public readonly ApprovalInstanceStep $step) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'approval_task_sla_warning',
            'approval_instance_step_id' => $this->step->getKey(),
            'purchase_request_id' => $this->step->approvalInstance?->purchase_request_id,
            'pr_number' => $this->step->approvalInstance?->purchaseRequest?->pr_number,
            'step' => $this->step->label,
            'due_at' => $this->step->due_at?->toISOString(),
        ];
    }
}
