<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverMappings\Pages;

use App\Filament\Resources\ApproverMappings\ApproverMappingResource;
use App\Models\ApproverMapping;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewApproverMapping extends ViewRecord
{
    protected static string $resource = ApproverMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ReplicateAction::make()
                ->excludeAttributes(['created_at', 'updated_at'])
                ->beforeReplicaSaved(function (ApproverMapping $replica): void {
                    $replica->is_active = false;
                    $replica->priority = $replica->priority + 1;
                })
                ->successNotificationTitle('Mapping diduplikasi (non-aktif)'),
            DeleteAction::make(),
        ];
    }
}
