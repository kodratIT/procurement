<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalInstanceStep;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final class FinanceApprovalDetailQuery
{
    public function for(ApprovalInstanceStep $task, ?User $actor = null): FinanceApprovalDetail
    {
        $actor ??= auth()->user();
        if (! $actor instanceof User || ! Gate::forUser($actor)->allows('view', $task)) {
            throw new AuthorizationException('You are not authorized to view this approval task.');
        }

        $task->load([
            'approvalInstance.purchaseRequest' => fn ($query) => $query->withoutGlobalScopes(),
            'approvalInstance.purchaseRequest.quotations.vendor',
            'approvalInstance.purchaseRequest.quotations.items',
            'approvalInstance.purchaseRequest.quotations.attachments',
            'approvalInstance.purchaseRequest.quotationRecommendations.vendor',
            'approvalInstance.purchaseRequest.quotationRecommendations.quotation',
            'approvalInstance.purchaseRequest.quotationRecommendations.attachments',
            'approvalInstance.purchaseRequest.statusHistories.actor',
            'approvalInstance.purchaseRequest.attachments',
            'approvalInstance.histories.actor',
        ]);

        $instance = $task->approvalInstance;
        $request = $instance->purchaseRequest;
        $workflowSnapshot = (array) data_get($instance->context, 'workflow_snapshot', []);
        $budgetContext = [
            'owner_office_id' => data_get($instance->context, 'budget_owner_office_id', $request->costCenter?->office_id ?? $request->office_id),
            'owner_office_name' => $request->costCenter?->office?->name ?? $request->office?->name,
            'amount' => (string) $request->total_amount,
            'check_required' => (bool) data_get($task->context, 'workflow_settings.budget_check.required', false),
            'check' => data_get($task->context, 'workflow_settings.budget_check', []),
        ];

        return new FinanceApprovalDetail(
            task: $task,
            purchaseRequest: $request,
            quotations: $request->quotations,
            recommendations: $request->quotationRecommendations,
            edits: $request->statusHistories,
            attachments: $request->attachments,
            priorDecisions: $instance->histories,
            budgetContext: $budgetContext,
            workflowSnapshot: $workflowSnapshot,
        );
    }
}
