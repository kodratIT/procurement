<?php

namespace App\Filament\Resources\ApproverMappings\Pages;

use App\Filament\Resources\ApproverMappings\ApproverMappingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApproverMappings extends ListRecords
{
    protected static string $resource = ApproverMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
