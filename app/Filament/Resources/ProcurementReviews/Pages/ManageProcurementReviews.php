<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcurementReviews\Pages;

use App\Filament\Resources\ProcurementReviewResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageProcurementReviews extends ManageRecords
{
    protected static string $resource = ProcurementReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
