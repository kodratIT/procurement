<?php

namespace App\Filament\Resources\ApproverMappings\Pages;

use App\Filament\Resources\ApproverMappings\ApproverMappingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateApproverMapping extends CreateRecord
{
    protected static string $resource = ApproverMappingResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Approver mapping berhasil dibuat';
    }
}
