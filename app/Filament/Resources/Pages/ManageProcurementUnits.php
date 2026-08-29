<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\ProcurementUnitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProcurementUnits extends ManageRecords
{
    protected static string $resource = ProcurementUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
