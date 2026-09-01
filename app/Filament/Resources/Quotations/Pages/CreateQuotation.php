<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations\Pages;

use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\PurchaseRequest;
use App\Services\QuotationComparisonService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateQuotation extends CreateRecord
{
    protected static string $resource = QuotationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $request = PurchaseRequest::query()->findOrFail((int) $data['purchase_request_id']);

        return app(QuotationComparisonService::class)->recordQuotation(
            $request,
            $data,
            auth()->user(),
        );
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Quotation berhasil dicatat';
    }
}
