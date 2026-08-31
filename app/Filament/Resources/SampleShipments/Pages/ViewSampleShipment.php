<?php

declare(strict_types=1);

namespace App\Filament\Resources\SampleShipments\Pages;

use App\Enums\SampleShipmentCondition;
use App\Filament\Resources\SampleShipments\SampleShipmentResource;
use App\Models\SampleShipment;
use App\Services\SampleShipmentReceiptService;
use App\Services\SampleShipmentService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

final class ViewSampleShipment extends ViewRecord
{
    protected static string $resource = SampleShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('submit')
                ->label('Submit')
                ->visible(fn (SampleShipment $record): bool => $record->statusValue() === SampleShipment::STATUS_DRAFT)
                ->action(function (SampleShipment $record): void {
                    app(SampleShipmentService::class)->submit($record, auth()->user());
                    Notification::make()->title('Shipment submitted')->success()->send();
                }),
            Action::make('review')
                ->label('Procurement review')
                ->visible(fn (SampleShipment $record): bool => $record->statusValue() === SampleShipment::STATUS_SUBMITTED)
                ->action(function (SampleShipment $record): void {
                    app(SampleShipmentService::class)->review($record, auth()->user());
                    Notification::make()->title('Shipment moved to procurement review')->success()->send();
                }),
            Action::make('approve')
                ->visible(fn (SampleShipment $record): bool => $record->statusValue() === SampleShipment::STATUS_PROCUREMENT_REVIEW)
                ->action(function (SampleShipment $record): void {
                    app(SampleShipmentService::class)->approve($record, auth()->user());
                    Notification::make()->title('Shipment approved')->success()->send();
                }),
            Action::make('mark_shipped')
                ->label('Mark shipped')
                ->visible(fn (SampleShipment $record): bool => $record->statusValue() === SampleShipment::STATUS_APPROVED)
                ->action(function (SampleShipment $record): void {
                    app(SampleShipmentService::class)->ship($record, [], auth()->user());
                    Notification::make()->title('Shipment marked as shipped')->success()->send();
                }),
            Action::make('confirm_delivery')
                ->label('Confirm delivery')
                ->visible(fn (SampleShipment $record): bool => in_array($record->statusValue(), [SampleShipment::STATUS_SHIPPED, SampleShipment::STATUS_RECEIVED], true))
                ->form([
                    TextInput::make('quantity')->numeric()->minValue(0.01)->required(),
                    Select::make('condition')
                        ->options(collect(SampleShipmentCondition::values())->mapWithKeys(fn (string $value): array => [$value => str($value)->headline()])->all())
                        ->required(),
                    Select::make('disposition')->options([
                        'stored' => 'Stored',
                        'returned' => 'Returned',
                        'damaged' => 'Damaged',
                        'lost' => 'Lost',
                    ])->default('stored')->required(),
                    DatePicker::make('received_at')->default(now())->required(),
                    FileUpload::make('photo')->required()->storeFiles(false)->visibility('private'),
                    FileUpload::make('signature')->required()->storeFiles(false)->visibility('private'),
                ])
                ->action(function (SampleShipment $record, array $data): void {
                    app(SampleShipmentReceiptService::class)->confirm($record, [
                        ...$data,
                        'evidence' => [
                            ['type' => 'photo', 'file' => $data['photo']],
                            ['type' => 'signature', 'file' => $data['signature']],
                        ],
                    ], auth()->user());
                    Notification::make()->title('Delivery confirmed')->success()->send();
                }),
            Action::make('return')
                ->label('Mark returned')
                ->visible(fn (SampleShipment $record): bool => $record->statusValue() === SampleShipment::STATUS_CONFIRMED)
                ->action(fn (SampleShipment $record): SampleShipment => app(SampleShipmentService::class)->transition($record, SampleShipment::STATUS_RETURNED, auth()->user())),
            Action::make('store')
                ->label('Mark stored')
                ->visible(fn (SampleShipment $record): bool => in_array($record->statusValue(), [SampleShipment::STATUS_CONFIRMED, SampleShipment::STATUS_RETURNED], true))
                ->action(fn (SampleShipment $record): SampleShipment => app(SampleShipmentService::class)->transition($record, SampleShipment::STATUS_STORED, auth()->user())),
            Action::make('complete')
                ->visible(fn (SampleShipment $record): bool => in_array($record->statusValue(), [SampleShipment::STATUS_STORED, SampleShipment::STATUS_RETURNED], true))
                ->action(fn (SampleShipment $record): SampleShipment => app(SampleShipmentService::class)->transition($record, SampleShipment::STATUS_COMPLETE, auth()->user())),
        ];
    }
}
