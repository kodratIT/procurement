<?php

declare(strict_types=1);

namespace App\Filament\Resources\SampleShipments\Schemas;

use App\Models\Activity;
use App\Models\SampleShipment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class SampleShipmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Shipment')
                ->schema([
                    TextEntry::make('shipment_number')->label('Shipment number'),
                    TextEntry::make('status')->badge()->label('Status'),
                    TextEntry::make('approval_route')->label('Approval route')->formatStateUsing(fn (string $state): string => $state === SampleShipment::APPROVAL_ROUTE_FINANCE ? 'Procurement and finance' : 'Procurement only'),
                    TextEntry::make('purpose')->label('Purpose'),
                    TextEntry::make('senderOffice.name')->label('Sender office'),
                    TextEntry::make('receiverOffice.name')->label('Receiving office'),
                    TextEntry::make('sender.name')->label('Responsible sender'),
                    TextEntry::make('receiver.name')->label('Responsible receiver'),
                    TextEntry::make('requested_at')->date()->label('Requested'),
                    TextEntry::make('planned_ship_date')->date()->label('Planned ship date'),
                    TextEntry::make('shipped_at')->date()->label('Shipped'),
                    TextEntry::make('received_at')->date()->label('Received'),
                    TextEntry::make('confirmed_at')->date()->label('Confirmed'),
                    TextEntry::make('tracking_no')->label('Tracking'),
                    TextEntry::make('shipping_cost')->money('IDR')->label('Shipping cost'),
                    TextEntry::make('costCenter.name')->label('Cost center'),
                    TextEntry::make('condition')->badge()->label('Condition'),
                    TextEntry::make('ownership')->badge()->label('Ownership'),
                ])->columns(4),
            Section::make('Items')
                ->schema([
                    TextEntry::make('items')
                        ->label('Items and variants')
                        ->state(fn (SampleShipment $record): string => $record->items->map(fn ($item): string => sprintf(
                            '%s%s — %s (%s) — %s',
                            $item->procurementItem?->name ?? 'Unknown item',
                            $item->procurementVariant === null ? '' : ' / '.$item->procurementVariant->name.' '.$item->procurementVariant->value,
                            $item->quantity,
                            $item->conditionValue(),
                            $item->ownership,
                        ))->implode("\n") ?: 'No items recorded.')
                        ->columnSpanFull(),
                ]),
            Section::make('Receipt')
                ->schema([
                    TextEntry::make('receipt.received_at')->date()->label('Receipt date'),
                    TextEntry::make('receipt.quantity')->label('Received quantity'),
                    TextEntry::make('receipt.condition')->badge()->label('Received condition'),
                    TextEntry::make('receipt.disposition')->badge()->label('Disposition'),
                    TextEntry::make('receipt.receiver.name')->label('Confirmed by'),
                ])->columns(3),
            Section::make('Status timeline')
                ->schema([
                    TextEntry::make('timeline')
                        ->state(fn (SampleShipment $record): string => Activity::query()
                            ->with('causer')
                            ->where('subject_type', SampleShipment::class)
                            ->where('subject_id', $record->getKey())
                            ->latest()
                            ->get()
                            ->map(fn (Activity $activity): string => sprintf(
                                '%s — %s — %s',
                                $activity->created_at?->format('Y-m-d H:i:s') ?? '-',
                                $activity->causer?->name ?? 'System',
                                $activity->description ?: $activity->event ?: 'Activity',
                            ))
                            ->implode("\n") ?: 'No timeline entries recorded.')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
