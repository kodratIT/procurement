<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowVersionResource\Pages;

use App\Filament\Resources\WorkflowVersionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkflowVersion extends EditRecord
{
    protected static string $resource = WorkflowVersionResource::class;

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
        return 'Workflow version berhasil diperbarui';
    }
}
