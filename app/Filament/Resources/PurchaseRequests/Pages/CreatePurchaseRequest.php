<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests\Pages;

use App\Filament\Resources\PurchaseRequestResource;
use App\Services\ProcurementRequestDraftSaver;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchaseRequest extends CreateRecord
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ProcurementRequestDraftSaver::class)->save($data);
    }
}
