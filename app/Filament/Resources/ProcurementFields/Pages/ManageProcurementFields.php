<?php

namespace App\Filament\Resources\ProcurementFields\Pages;

use App\Filament\Resources\ProcurementFields\ProcurementFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProcurementFields extends ManageRecords
{
    protected static string $resource = ProcurementFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
