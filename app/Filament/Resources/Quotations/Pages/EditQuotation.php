<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations\Pages;

use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Quotation;
use App\Services\QuotationComparisonService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditQuotation extends EditRecord
{
    protected static string $resource = QuotationResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord()->load('items');
        $data['items'] = $record->items->map(fn ($item): array => [
            'purchase_request_item_id' => $item->purchase_request_item_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'description' => $item->description,
            'notes' => $item->notes,
        ])->all();
        $data['attachments'] = [];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Quotation) {
            abort(404);
        }

        return app(QuotationComparisonService::class)->updateQuotation($record, $data, auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
