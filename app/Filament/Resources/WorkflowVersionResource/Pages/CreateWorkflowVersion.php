<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowVersionResource\Pages;

use App\Filament\Resources\WorkflowVersionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkflowVersion extends CreateRecord
{
    protected static string $resource = WorkflowVersionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Workflow version berhasil dibuat';
    }
}
