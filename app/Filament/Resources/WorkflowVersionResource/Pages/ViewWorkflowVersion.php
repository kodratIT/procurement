<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowVersionResource\Pages;

use App\Enums\WorkflowVersionStatus;
use App\Filament\Resources\WorkflowVersionResource;
use App\Models\WorkflowVersion;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewWorkflowVersion extends ViewRecord
{
    protected static string $resource = WorkflowVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ReplicateAction::make()
                ->excludeAttributes(['created_at', 'updated_at', 'activated_at', 'retired_at'])
                ->beforeReplicaSaved(function (WorkflowVersion $replica): void {
                    $replica->version_number = $replica->version_number + 1;
                    $replica->status = WorkflowVersionStatus::Draft;
                    $replica->activated_at = null;
                    $replica->retired_at = null;
                })
                ->successNotificationTitle('Versi diduplikasi sebagai draft'),
            DeleteAction::make(),
        ];
    }
}
