<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowStepResource\Pages;

use App\Filament\Resources\WorkflowStepResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkflowStep extends EditRecord
{
    protected static string $resource = WorkflowStepResource::class;

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
        return 'Workflow stage berhasil diperbarui';
    }
}
