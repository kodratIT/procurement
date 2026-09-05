<?php

declare(strict_types=1);

namespace App\Flow\Nodes\Actions;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\WorkflowResolver;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Packstub\Flow\Nodes\Action;
use Packstub\Flow\Nodes\Concerns\InterpolatesPlaceholders;

/**
 * Hybrid example: Notify the resolved next approver of a Purchase Request.
 * Uses the existing WorkflowResolver to find the approver, then sends a
 * Filament database notification + respects placeholders.
 */
class NotifyNextApproverAction extends Action
{
    use InterpolatesPlaceholders;

    public function getName(): string
    {
        return 'Notify Next Approver (PR)';
    }

    public function getDescription(): string
    {
        return 'Resolve approver via WorkflowResolver and send Filament notification. Hybrid with existing approval chain.';
    }

    public function getIcon(): ?string
    {
        return 'heroicon-o-bell-alert';
    }

    public function getFormSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Judul Notifikasi')
                ->default('Purchase Request menunggu approval: {{ model.pr_number }}')
                ->helperText('Bisa pakai placeholder {{ model.pr_number }}, {{ model.total_amount }}, {{ model.title }}')
                ->required(),
            Textarea::make('body')
                ->label('Isi Notifikasi')
                ->default('PR {{ model.pr_number }} sebesar {{ model.total_amount }} perlu approval Anda.')
                ->rows(3),
        ];
    }

    public function handle(array $config, array $payload): void
    {
        $model = $payload['model'] ?? null;

        if (! $model instanceof PurchaseRequest) {
            // Fallback: try to load PR from payload subject
            $this->output(['skipped' => 'Payload model is not PurchaseRequest'], ['skipped' => true]);

            return;
        }

        $resolver = app(WorkflowResolver::class);
        $submitter = $model->requester ?? $payload['user'] ?? null;

        // Preview resolution without throwing if missing approver
        $result = $resolver->preview($model, $submitter ?? $model->requester);

        $firstStep = $result['steps'][0] ?? null;

        if (! $firstStep || empty($firstStep['user']['id'])) {
            $this->output(['error' => 'No approver resolved'], ['status' => 'unresolved']);

            return;
        }

        $approverId = $firstStep['user']['id'];
        $approver = User::find($approverId);

        if (! $approver) {
            $this->output(['error' => 'Approver user not found'], ['status' => 'error']);

            return;
        }

        $title = $this->interpolate($config['title'] ?? 'PR {{ model.pr_number }}', $payload);
        $body = $this->interpolate($config['body'] ?? '', $payload);

        Notification::make()
            ->title($title)
            ->body($body)
            ->sendToDatabase($approver);

        $this->output([
            'notified_user_id' => $approver->getKey(),
            'step_label' => $firstStep['label'] ?? null,
            'workflow_reference' => $result['reference'] ?? null,
        ], [
            'notified' => $approver->name,
        ]);
    }
}
