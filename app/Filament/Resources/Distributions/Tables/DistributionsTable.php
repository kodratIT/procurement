<?php

declare(strict_types=1);

namespace App\Filament\Resources\Distributions\Tables;

use App\Models\Distribution;
use App\Services\DistributionService;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class DistributionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch.code')->label('Batch')->searchable()->sortable(),
                TextColumn::make('batch.office.name')->label('Office')->searchable()->sortable(),
                TextColumn::make('distributed_at')->label('Date')->date()->sortable(),
                TextColumn::make('receipt_mode')
                    ->label('Receipt mode')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Distribution::RECEIPT_MODE_INDIVIDUAL => 'Individual',
                        default => 'Batch',
                    }),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('total_quantity')
                    ->label('Total quantity')
                    ->state(function (Distribution $record): string {
                        return array_reduce(
                            app(DistributionService::class)->totals($record),
                            static fn (string $total, string $quantity): string => bcadd($total, $quantity, 2),
                            '0.00',
                        );
                    })
                    ->sortable(false),
                TextColumn::make('updated_at')->label('Last updated')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('receipt_mode')->label('Receipt mode')->options([
                    Distribution::RECEIPT_MODE_BATCH => 'Batch',
                    Distribution::RECEIPT_MODE_INDIVIDUAL => 'Individual',
                ]),
                SelectFilter::make('status')->options(array_combine(Distribution::STATUSES, Distribution::STATUSES)),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
