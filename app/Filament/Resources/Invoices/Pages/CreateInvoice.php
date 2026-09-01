<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\PurchaseOrder;
use App\Services\InvoiceMatchingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $order = PurchaseOrder::query()->findOrFail((int) $data['purchase_order_id']);

        return app(InvoiceMatchingService::class)->record($order, $data, auth()->user());
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Invoice recorded and matched';
    }
}
