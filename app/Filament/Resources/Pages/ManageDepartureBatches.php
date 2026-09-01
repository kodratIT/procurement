<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\DepartureBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDepartureBatches extends ManageRecords
{
    protected static string $resource = DepartureBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
