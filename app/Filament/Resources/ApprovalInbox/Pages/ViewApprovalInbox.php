<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalInbox\Pages;

use App\Filament\Resources\ApprovalInboxResource;
use App\Models\ApprovalInstanceStep;
use App\Services\ApprovalActionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

final class ViewApprovalInbox extends ViewRecord
{
    protected static string $resource = ApprovalInboxResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->authorize(fn (ApprovalInstanceStep $record): bool => $this->canDecide($record))
                ->action(fn (ApprovalInstanceStep $record): ApprovalInstanceStep => app(ApprovalActionService::class)->approve($record)),
            Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->requiresConfirmation()
                ->schema([Textarea::make('notes')->label('Catatan')->required()])
                ->authorize(fn (ApprovalInstanceStep $record): bool => $this->canDecide($record))
                ->action(fn (ApprovalInstanceStep $record, array $data): ApprovalInstanceStep => app(ApprovalActionService::class)->reject($record, auth()->user(), (string) $data['notes'])),
            Action::make('return')
                ->label('Return')
                ->color('warning')
                ->requiresConfirmation()
                ->schema([Textarea::make('notes')->label('Catatan')->required()])
                ->authorize(fn (ApprovalInstanceStep $record): bool => $this->canDecide($record))
                ->action(fn (ApprovalInstanceStep $record, array $data): ApprovalInstanceStep => app(ApprovalActionService::class)->returnStep($record, auth()->user(), (string) $data['notes'])),
        ];
    }

    private function canDecide(ApprovalInstanceStep $record): bool
    {
        return $record->status === 'pending'
            && Gate::forUser(auth()->user())->allows('view', $record);
    }
}
