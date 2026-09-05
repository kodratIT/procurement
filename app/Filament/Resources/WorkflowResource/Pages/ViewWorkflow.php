<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowResource\Pages;

use App\Filament\Resources\WorkflowResource;
use App\Models\Workflow;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewWorkflow extends ViewRecord
{
    protected static string $resource = WorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ReplicateAction::make()
                ->excludeAttributes(['created_at', 'updated_at'])
                ->beforeReplicaSaved(function (Workflow $replica): void {
                    $replica->code = $replica->code.'-copy';
                    $replica->is_active = false;
                })
                ->successNotificationTitle('Workflow diduplikasi (non-aktif)'),
            DeleteAction::make(),
        ];
    }
}
