<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcurementCategories\Pages;

use App\Filament\Resources\ProcurementCategoryResource;
use App\Models\ProcurementCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewProcurementCategory extends ViewRecord
{
    protected static string $resource = ProcurementCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ReplicateAction::make()
                ->label('Duplikat')
                ->excludeAttributes(['created_at', 'updated_at', 'disabled_at'])
                ->beforeReplicaSaved(function (ProcurementCategory $replica): void {
                    $replica->code = $replica->code.'-COPY';
                    $replica->name = $replica->name.' (Copy)';
                    $replica->is_active = false;
                    $replica->disabled_at = now();
                })
                ->successNotificationTitle('Kategori diduplikasi (non-aktif)'),
            DeleteAction::make(),
        ];
    }
}
