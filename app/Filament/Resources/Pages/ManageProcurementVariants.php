<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\ProcurementVariantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProcurementVariants extends ManageRecords
{
    protected static string $resource = ProcurementVariantResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
