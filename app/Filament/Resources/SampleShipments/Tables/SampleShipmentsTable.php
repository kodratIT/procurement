<?php

declare(strict_types=1);

namespace App\Filament\Resources\SampleShipments\Tables;

use App\Models\SampleShipment;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class SampleShipmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('shipment_number')->label('Shipment')->searchable()->sortable(),
                TextColumn::make('senderOffice.name')->label('From')->searchable()->sortable(),
                TextColumn::make('receiverOffice.name')->label('To')->searchable()->sortable(),
                TextColumn::make('purpose')->label('Purpose')->limit(35)->searchable(),
                TextColumn::make('tracking_no')->label('Tracking')->searchable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('ownership')->label('Ownership')->badge(),
                TextColumn::make('shipping_cost')->label('Shipping cost')->money('IDR')->sortable(),
                TextColumn::make('updated_at')->label('Last updated')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine(SampleShipment::STATUSES, SampleShipment::STATUSES)),
                SelectFilter::make('approval_route')->options([
                    SampleShipment::APPROVAL_ROUTE_PROCUREMENT => 'Procurement only',
                    SampleShipment::APPROVAL_ROUTE_FINANCE => 'Procurement and finance',
                ]),
                SelectFilter::make('ownership')->options(array_combine(SampleShipment::OWNERSHIPS, SampleShipment::OWNERSHIPS)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
