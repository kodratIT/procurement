<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests\Pages;

use App\Filament\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use App\Services\PurchaseRequestCancellationService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

final class ViewPurchaseRequest extends ViewRecord
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (PurchaseRequest $record): bool => $record->isCorrectable()),
            DeleteAction::make()
                ->visible(fn (PurchaseRequest $record): bool => $record->status === PurchaseRequest::STATUS_DRAFT),
            Action::make('cancel')
                ->label('Batalkan')
                ->color('gray')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Batalkan PR?')
                ->modalDescription('PR akan dibatalkan dan tidak dilanjutkan.')
                ->schema([Textarea::make('notes')->label('Alasan Pembatalan')->required()->minLength(5)])
                ->visible(fn (PurchaseRequest $record): bool => app(PurchaseRequestCancellationService::class)->canCancel($record))
                ->action(function (PurchaseRequest $record, array $data): void {
                    app(PurchaseRequestCancellationService::class)->cancel($record, auth()->user(), (string) $data['notes']);
                }),
        ];
    }
}
