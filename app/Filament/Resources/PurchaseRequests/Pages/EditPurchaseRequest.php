<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests\Pages;

use App\Filament\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use App\Services\ProcurementRequestDraftSaver;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPurchaseRequest extends EditRecord
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['fields'] = $this->getRecord()->fieldValues
            ->mapWithKeys(fn ($value): array => [$value->field_key => $value->value])
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof PurchaseRequest) {
            abort(404);
        }

        return app(ProcurementRequestDraftSaver::class)->save($data, $record);
    }
}
