<?php

declare(strict_types=1);

namespace App\Flow\Nodes\Actions;

use App\Models\PurchaseRequest;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Packstub\Flow\Nodes\Action;
use Packstub\Flow\Nodes\Concerns\InterpolatesPlaceholders;

class UpdatePurchaseRequestStatusAction extends Action
{
    use InterpolatesPlaceholders;

    public function getName(): string
    {
        return 'Update PR Status';
    }

    public function getDescription(): string
    {
        return 'Update PurchaseRequest status safely (e.g., auto-approve if total < threshold).';
    }

    public function getIcon(): ?string
    {
        return 'heroicon-o-arrow-path';
    }

    public function getFormSchema(): array
    {
        return [
            Select::make('status')
                ->label('Target Status')
                ->options([
                    'procurement_review' => 'Procurement Review',
                    'pending_approval' => 'Pending Approval',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ])
                ->required(),
            TextInput::make('reason')
                ->label('Reason / Note')
                ->helperText('Optional, supports placeholders {{ model.pr_number }}')
                ->placeholder('Auto-approved by automation'),
        ];
    }

    public function handle(array $config, array $payload): void
    {
        $model = $payload['model'] ?? null;

        if (! $model instanceof PurchaseRequest) {
            $this->output(['skipped' => 'Not a PurchaseRequest'], ['skipped' => true]);

            return;
        }

        $status = $this->interpolate($config['status'] ?? '', $payload);

        // Guard: only allow valid PR statuses
        if (! in_array($status, PurchaseRequest::STATUSES, true)) {
            $this->output(['error' => "Invalid status: {$status}"], ['error' => $status]);

            return;
        }

        // Use quiet update to avoid infinite loop unless desired
        $model->updateQuietly(['status' => $status]);

        $this->output([
            'pr_id' => $model->getKey(),
            'new_status' => $status,
            'pr_number' => $model->pr_number,
        ]);
    }
}
