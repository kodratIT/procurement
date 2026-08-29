<?php

namespace App\Filament\Resources;

use App\Filament\Exports\ProcurementVariantExporter;
use App\Models\ProcurementVariant;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProcurementVariantResource extends Resource
{
    protected static ?string $model = ProcurementVariant::class;

    protected static ?string $navigationLabel = 'Varian / Ukuran';

    protected static ?string $modelLabel = 'varian';

    protected static ?string $pluralModelLabel = 'varian';

    public static function form(Schema $s): Schema
    {
        return $s->components([
            Select::make('item_id')->relationship('item', 'name', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload()->required(),
            Select::make('variation_type')->label('Tipe Variasi')->options([
                ProcurementVariant::TYPE_UKURAN => 'Ukuran',
                ProcurementVariant::TYPE_WARNA => 'Warna',
                ProcurementVariant::TYPE_BAHAN => 'Bahan',
            ])->default(ProcurementVariant::TYPE_UKURAN)->required(),
            TextInput::make('code')->required()->maxLength(50),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('value')->maxLength(255),
            Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $t): Table
    {
        return $t->columns([
            TextColumn::make('item.name')->label('Item')->searchable()->sortable(),
            TextColumn::make('variation_type')->label('Tipe')->badge()->sortable(),
            TextColumn::make('code')->searchable()->sortable(),
            TextColumn::make('name')->searchable(),
            TextColumn::make('value')->sortable(),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->filters([
            SelectFilter::make('item')->relationship('item', 'name')->searchable()->preload(),
            SelectFilter::make('variation_type')->label('Tipe Variasi')->options([
                ProcurementVariant::TYPE_UKURAN => 'Ukuran',
                ProcurementVariant::TYPE_WARNA => 'Warna',
                ProcurementVariant::TYPE_BAHAN => 'Bahan',
            ]),
            SelectFilter::make('is_active')->label('Status Aktif')->options(['1' => 'Aktif', '0' => 'Nonaktif']),
        ])->recordActions([EditAction::make(), DeleteAction::make()])->toolbarActions([ExportBulkAction::make()->exporter(ProcurementVariantExporter::class), DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageProcurementVariants::route('/')];
    }
}
