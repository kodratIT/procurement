<?php

namespace App\Filament\Resources\UmrahBatches\Pages;

use App\Filament\Resources\UmrahBatches\UmrahBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUmrahBatches extends ManageRecords
{
    protected static string $resource = UmrahBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
