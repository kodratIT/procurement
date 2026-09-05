<?php

namespace App\Filament\Resources\ApproverDelegations\Pages;

use App\Filament\Resources\ApproverDelegations\ApproverDelegationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateApproverDelegation extends CreateRecord
{
    protected static string $resource = ApproverDelegationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Approver delegation berhasil dibuat';
    }
}
