<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverDelegations\Pages;

use App\Filament\Resources\ApproverDelegations\ApproverDelegationResource;
use App\Models\ApproverDelegation;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewApproverDelegation extends ViewRecord
{
    protected static string $resource = ApproverDelegationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ReplicateAction::make()
                ->excludeAttributes(['created_at', 'updated_at'])
                ->beforeReplicaSaved(function (ApproverDelegation $replica): void {
                    $replica->is_active = false;
                })
                ->successNotificationTitle('Delegasi diduplikasi (non-aktif)'),
            DeleteAction::make(),
        ];
    }
}
