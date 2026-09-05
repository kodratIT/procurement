<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalInbox\Pages;

use App\Filament\Resources\ApprovalInboxResource;
use App\Models\ApprovalInstanceStep;
use App\Models\PurchaseRequest;
use App\Services\ApprovalActionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

final class ViewApprovalInbox extends ViewRecord
{
    protected static string $resource = ApprovalInboxResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Setujui')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Setujui PR?')
                ->modalDescription('Setelah disetujui, PR akan dilanjutkan ke tahap berikutnya.')
                ->visible(fn (PurchaseRequest $record): bool => ApprovalInboxResource::actionableTask($record) instanceof ApprovalInstanceStep)
                ->successRedirectUrl(ApprovalInboxResource::getUrl('index'))
                ->action(function (PurchaseRequest $record): void {
                    $step = ApprovalInboxResource::actionableTask($record);
                    if (! $step instanceof ApprovalInstanceStep) {
                        throw ValidationException::withMessages(['approval' => 'Tugas persetujuan sudah tidak tersedia.']);
                    }

                    app(ApprovalActionService::class)->approve($step, auth()->user(), 'Disetujui');
                }),
            Action::make('return')
                ->label('Butuh Perbaikan')
                ->color('warning')
                ->icon('heroicon-o-pencil-square')
                ->requiresConfirmation()
                ->modalHeading('Kembalikan untuk Perbaikan?')
                ->modalDescription('PR akan dikembalikan ke pengaju untuk dikoreksi dan diajukan ulang.')
                ->schema([Textarea::make('notes')->label('Alasan Perbaikan')->required()->minLength(10)->placeholder('Jelaskan apa yang perlu diperbaiki...')])
                ->visible(fn (PurchaseRequest $record): bool => ApprovalInboxResource::actionableTask($record) instanceof ApprovalInstanceStep)
                ->successRedirectUrl(ApprovalInboxResource::getUrl('index'))
                ->action(function (PurchaseRequest $record, array $data): void {
                    $step = ApprovalInboxResource::actionableTask($record);
                    if (! $step instanceof ApprovalInstanceStep) {
                        throw ValidationException::withMessages(['approval' => 'Tugas persetujuan sudah tidak tersedia.']);
                    }

                    app(ApprovalActionService::class)->returnStep($step, auth()->user(), (string) $data['notes']);
                }),
            Action::make('reject')
                ->label('Tolak')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->modalHeading('Tolak PR?')
                ->modalDescription('PR yang ditolak tidak bisa dilanjutkan, pengaju harus buat PR baru.')
                ->schema([Textarea::make('notes')->label('Alasan Penolakan')->required()->minLength(10)->placeholder('Jelaskan alasan penolakan...')])
                ->visible(fn (PurchaseRequest $record): bool => ApprovalInboxResource::actionableTask($record) instanceof ApprovalInstanceStep)
                ->successRedirectUrl(ApprovalInboxResource::getUrl('index'))
                ->action(function (PurchaseRequest $record, array $data): void {
                    $step = ApprovalInboxResource::actionableTask($record);
                    if (! $step instanceof ApprovalInstanceStep) {
                        throw ValidationException::withMessages(['approval' => 'Tugas persetujuan sudah tidak tersedia.']);
                    }

                    app(ApprovalActionService::class)->reject($step, auth()->user(), (string) $data['notes']);
                }),
        ];
    }
}
