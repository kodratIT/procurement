<?php

namespace App\Filament\Resources\ApproverDelegations\Pages;

use App\Filament\Resources\ApproverDelegations\ApproverDelegationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditApproverDelegation extends EditRecord
{
    protected static string $resource = ApproverDelegationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
