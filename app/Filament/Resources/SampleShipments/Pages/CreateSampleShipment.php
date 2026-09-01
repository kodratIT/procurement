<?php

declare(strict_types=1);

namespace App\Filament\Resources\SampleShipments\Pages;

use App\Filament\Resources\SampleShipments\SampleShipmentResource;
use App\Models\PurchaseOrder;
use App\Services\SampleShipmentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateSampleShipment extends CreateRecord
{
    protected static string $resource = SampleShipmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        if (($data['purchase_order_id'] ?? null) !== null) {
            PurchaseOrder::query()->findOrFail((int) $data['purchase_order_id']);
        }

        return app(SampleShipmentService::class)->create($data, auth()->user());
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Sample shipment created';
    }
}
