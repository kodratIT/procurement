<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcurementCategories\Pages;

use App\Filament\Resources\ProcurementCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProcurementCategory extends CreateRecord
{
    protected static string $resource = ProcurementCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Kategori berhasil dibuat';
    }
}
