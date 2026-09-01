<?php

declare(strict_types=1);

namespace App\Filament\Resources\SampleShipments\Pages;

use App\Filament\Resources\SampleShipments\SampleShipmentResource;
use App\Models\SampleShipment;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditSampleShipment extends EditRecord
{
    protected static string $resource = SampleShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()->visible(fn (SampleShipment $record): bool => $record->statusValue() === SampleShipment::STATUS_DRAFT),
        ];
    }
}
