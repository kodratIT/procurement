<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcurementReviews\Pages;

use App\Filament\Resources\ProcurementReviewResource;
use App\Models\PurchaseRequest;
use App\Services\ProcurementReviewService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditProcurementReview extends EditRecord
{
    protected static string $resource = ProcurementReviewResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord()->load(['items', 'fieldValues']);
        $data['items'] = $record->items->map(fn ($item): array => [
            'id' => $item->getKey(),
            'item_name' => $item->item_name,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'description' => $item->description,
            'specifications' => $item->specifications,
            'notes' => $item->notes,
        ])->all();
        $data['fields'] = $record->fieldValues
            ->mapWithKeys(fn ($value): array => [$value->field_key => $value->value])
            ->all();
        $data['review_reason'] = null;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof PurchaseRequest) {
            abort(404);
        }

        $reason = (string) ($data['review_reason'] ?? '');
        unset($data['review_reason'], $data['pr_number'], $data['requester'], $data['office'], $data['branch'], $data['department']);

        return app(ProcurementReviewService::class)->edit(
            $record,
            [
                'items' => $data['items'] ?? [],
                'fields' => $data['fields'] ?? [],
                'vendor_id' => $data['vendor_id'] ?? null,
            ],
            $reason,
        );
    }
}
