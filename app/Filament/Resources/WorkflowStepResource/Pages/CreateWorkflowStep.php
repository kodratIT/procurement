<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowStepResource\Pages;

use App\Filament\Resources\WorkflowStepResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkflowStep extends CreateRecord
{
    protected static string $resource = WorkflowStepResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Workflow stage berhasil dibuat';
    }
}
