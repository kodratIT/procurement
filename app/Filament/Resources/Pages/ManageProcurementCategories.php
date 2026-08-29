<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\ProcurementCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProcurementCategories extends ManageRecords
{
    protected static string $resource = ProcurementCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
