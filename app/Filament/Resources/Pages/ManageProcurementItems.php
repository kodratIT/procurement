<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\ProcurementItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProcurementItems extends ManageRecords
{
    protected static string $resource = ProcurementItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
