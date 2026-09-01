<?php

declare(strict_types=1);

namespace App\Filament\Resources\SampleShipments\Pages;

use App\Filament\Resources\SampleShipments\SampleShipmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListSampleShipments extends ListRecords
{
    protected static string $resource = SampleShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
