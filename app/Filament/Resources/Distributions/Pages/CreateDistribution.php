<?php

declare(strict_types=1);

namespace App\Filament\Resources\Distributions\Pages;

use App\Filament\Resources\Distributions\DistributionResource;
use App\Models\UmrahBatch;
use App\Services\DistributionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateDistribution extends CreateRecord
{
    protected static string $resource = DistributionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $batch = UmrahBatch::query()
            ->withoutGlobalScopes()
            ->findOrFail((int) $data['umrah_batch_id']);

        return app(DistributionService::class)->record($batch, $data, auth()->user());
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Distribution recorded';
    }
}
