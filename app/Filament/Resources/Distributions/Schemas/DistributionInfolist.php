<?php

declare(strict_types=1);

namespace App\Filament\Resources\Distributions\Schemas;

use App\Models\Activity;
use App\Models\Distribution;
use App\Models\ProcurementItem;
use App\Services\DistributionService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class DistributionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Distribution')
                ->schema([
                    TextEntry::make('batch.code')->label('Batch code'),
                    TextEntry::make('batch.name')->label('Batch'),
                    TextEntry::make('batch.office.name')->label('Office'),
                    TextEntry::make('distributed_at')->label('Distribution date')->date(),
                    TextEntry::make('receipt_mode')
                        ->label('Receipt mode')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            Distribution::RECEIPT_MODE_INDIVIDUAL => 'Individual',
                            default => 'Batch',
                        }),
                    TextEntry::make('status')->label('Status')->badge(),
                ])->columns(3),
            Section::make('Distributed items')
                ->schema([
                    TextEntry::make('items')
                        ->label('Items and quantities')
                        ->state(fn (Distribution $record): string => self::distributionItems($record))
                        ->columnSpanFull(),
                    TextEntry::make('batch_totals')
                        ->label('Batch totals to date')
                        ->state(fn (Distribution $record): string => self::batchTotals($record))
                        ->columnSpanFull(),
                ])->columns(2),
            Section::make('History')
                ->schema([
                    TextEntry::make('history')
                        ->label('Status and activity history')
                        ->state(fn (Distribution $record): string => self::history($record))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function distributionItems(Distribution $record): string
    {
        return $record->items
            ->map(fn ($item): string => sprintf(
                '%s — %s %s',
                $item->procurementItem?->name ?? 'Unknown item',
                $item->quantity,
                $item->procurementItem?->unit?->name ?? 'units',
            ))
            ->implode("\n") ?: 'No items recorded.';
    }

    private static function batchTotals(Distribution $record): string
    {
        $totals = app(DistributionService::class)->batchTotals($record->batch);
        $names = ProcurementItem::query()
            ->whereIn('id', array_keys($totals))
            ->pluck('name', 'id');

        return collect($totals)
            ->map(fn (string $quantity, int $itemId): string => sprintf('%s — %s', $names[$itemId] ?? 'Item '.$itemId, $quantity))
            ->implode("\n") ?: 'No distributed quantities yet.';
    }

    private static function history(Distribution $record): string
    {
        return Activity::query()
            ->with('causer')
            ->where('subject_type', Distribution::class)
            ->where('subject_id', $record->getKey())
            ->latest()
            ->get()
            ->map(fn (Activity $activity): string => sprintf(
                '%s — %s — %s',
                $activity->created_at?->format('Y-m-d H:i:s') ?? '-',
                $activity->causer?->name ?? 'System',
                $activity->description ?: $activity->event ?: 'Activity',
            ))
            ->implode("\n") ?: 'No history recorded.';
    }
}
