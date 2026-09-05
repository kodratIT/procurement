<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcurementCategories\Pages;

use App\Filament\Resources\ProcurementCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProcurementCategory extends EditRecord
{
    protected static string $resource = ProcurementCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Kategori berhasil diperbarui';
    }
}
