<?php

declare(strict_types=1);

namespace App\Filament\Resources\Distributions\Schemas;

use App\Models\Distribution;
use App\Models\ProcurementItem;
use App\Models\UmrahBatch;
use App\Models\User;
use App\Services\DistributionService;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

final class DistributionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('umrah_batch_id')
                    ->label('Umrah batch')
                    ->options(fn (): array => self::batchOptions())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                DatePicker::make('distributed_at')
                    ->label('Distribution date')
                    ->default(now())
                    ->required(),
                Select::make('receipt_mode')
                    ->label('Receipt mode')
                    ->options([
                        Distribution::RECEIPT_MODE_BATCH => 'Batch receipt',
                        Distribution::RECEIPT_MODE_INDIVIDUAL => 'Individual pilgrim receipts',
                    ])
                    ->default(Distribution::RECEIPT_MODE_BATCH)
                    ->required(),
                Select::make('status')
                    ->label('Status')
                    ->options(array_combine(Distribution::STATUSES, Distribution::STATUSES))
                    ->default(Distribution::STATUS_RECORDED)
                    ->required(),
                Repeater::make('lines')
                    ->label('Items')
                    ->schema([
                        Select::make('procurement_item_id')
                            ->label('Item')
                            ->options(fn (Get $get): array => self::itemOptions($get->integer('../../umrah_batch_id', isNullable: true)))
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->defaultItems(1)
                    ->distinct(),
            ])
            ->columns(2);
    }

    /** @return array<int|string, string> */
    private static function batchOptions(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [];
        }

        return app(MultiOfficeAuthorization::class)->scopeForUser(
            UmrahBatch::query()
                ->active()
                ->where('status', '!=', UmrahBatch::STATUS_CANCELLED)
                ->with('office')
                ->orderByDesc('departure_date'),
            $user,
            ProcurementPermissions::CREATE,
        )->get()->mapWithKeys(fn (UmrahBatch $batch): array => [
            $batch->id => sprintf(
                '%s — %s%s',
                $batch->code,
                $batch->name,
                $batch->office?->name ? ' — '.$batch->office->name : '',
            ),
        ])->all();
    }

    /** @return array<int|string, string> */
    private static function itemOptions(mixed $batchId): array
    {
        if (! is_numeric($batchId)) {
            return [];
        }

        $batch = UmrahBatch::query()->find((int) $batchId);
        if (! $batch instanceof UmrahBatch) {
            return [];
        }

        $availability = app(DistributionService::class)->availability($batch);

        return ProcurementItem::query()
            ->active()
            ->with('unit')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ProcurementItem $item): array => [
                $item->id => sprintf(
                    '%s — remaining received: %s%s',
                    $item->name,
                    $availability[$item->id] ?? '0.00',
                    $item->unit?->name ? ' '.$item->unit->name : '',
                ),
            ])->all();
    }
}
