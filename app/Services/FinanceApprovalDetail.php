<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalInstanceStep;
use App\Models\PurchaseRequest;
use Illuminate\Support\Collection;

final readonly class FinanceApprovalDetail
{
    /**
     * @param  Collection<int, mixed>  $quotations
     * @param  Collection<int, mixed>  $recommendations
     * @param  Collection<int, mixed>  $edits
     * @param  Collection<int, mixed>  $attachments
     * @param  Collection<int, mixed>  $priorDecisions
     * @param  array<string, mixed>  $budgetContext
     * @param  array<string, mixed>  $workflowSnapshot
     */
    public function __construct(
        public ApprovalInstanceStep $task,
        public PurchaseRequest $purchaseRequest,
        public Collection $quotations,
        public Collection $recommendations,
        public Collection $edits,
        public Collection $attachments,
        public Collection $priorDecisions,
        public array $budgetContext,
        public array $workflowSnapshot,
    ) {}
}
