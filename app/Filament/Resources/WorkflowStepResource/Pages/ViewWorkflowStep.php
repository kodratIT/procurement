<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowStepResource\Pages;

use App\Filament\Resources\WorkflowStepResource;
use App\Models\WorkflowStep;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewWorkflowStep extends ViewRecord
{
    protected static string $resource = WorkflowStepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ReplicateAction::make()
                ->excludeAttributes(['created_at', 'updated_at'])
                ->beforeReplicaSaved(function (WorkflowStep $replica): void {
                    $replica->sequence = $replica->sequence + 1;
                })
                ->successNotificationTitle('Tahap diduplikasi'),
            DeleteAction::make(),
        ];
    }
}
