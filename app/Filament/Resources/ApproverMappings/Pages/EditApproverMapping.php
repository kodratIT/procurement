<?php

namespace App\Filament\Resources\ApproverMappings\Pages;

use App\Filament\Resources\ApproverMappings\ApproverMappingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditApproverMapping extends EditRecord
{
    protected static string $resource = ApproverMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Approver mapping berhasil diperbarui';
    }
}
