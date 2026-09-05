<?php

namespace App\Filament\Resources;

use App\Filament\Exports\ProcurementVariantExporter;
use App\Models\ProcurementVariant;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProcurementVariantResource extends Resource
{
    protected static ?string $model = ProcurementVariant::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $navigationLabel = 'Variants';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'varian';

    protected static ?string $pluralModelLabel = 'varian';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('item_id')
                    ->relationship('item', 'name', fn (Builder $query): Builder => $query->availableForNewTransactions())
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('variation_type')
                    ->label('Tipe Variasi')
                    ->options([
                        ProcurementVariant::TYPE_UKURAN => 'Ukuran',
                        ProcurementVariant::TYPE_WARNA => 'Warna',
                        ProcurementVariant::TYPE_BAHAN => 'Bahan',
                    ])
                    ->default(ProcurementVariant::TYPE_UKURAN)
                    ->required(),
                TextInput::make('code')->label('Kode')->required()->maxLength(50),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('value')->maxLength(255),
                KeyValue::make('attributes')
                    ->label('Atribut tambahan')
                    ->keyLabel('Atribut')
                    ->valueLabel('Nilai')
                    ->columnSpanFull(),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.name')->label('Item')->searchable()->sortable(),
                TextColumn::make('variation_type')->label('Tipe')->badge()->sortable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('value')->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('item')->relationship('item', 'name')->searchable()->preload(),
                SelectFilter::make('variation_type')->label('Tipe Variasi')->options([
                    ProcurementVariant::TYPE_UKURAN => 'Ukuran',
                    ProcurementVariant::TYPE_WARNA => 'Warna',
                    ProcurementVariant::TYPE_BAHAN => 'Bahan',
                ]),
                SelectFilter::make('is_active')->label('Status Aktif')->options(['1' => 'Aktif', '0' => 'Nonaktif']),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('deactivate')
                    ->label('Nonaktifkan')
                    ->requiresConfirmation()
                    ->visible(fn (ProcurementVariant $record): bool => $record->is_active)
                    ->authorize('deactivate')
                    ->action(fn (ProcurementVariant $record): bool => $record->deactivate()),
                Action::make('activate')
                    ->label('Aktifkan')
                    ->visible(fn (ProcurementVariant $record): bool => ! $record->is_active)
                    ->authorize('activate')
                    ->action(fn (ProcurementVariant $record): bool => $record->activate()),
            ])
            ->toolbarActions([
                ExportBulkAction::make()->exporter(ProcurementVariantExporter::class),
            ]);
    }

    public static function canAccess(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allows($user, 'ViewAny:ProcurementVariant'));
    }

    public static function canViewAny(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allows($user, 'ViewAny:ProcurementVariant'));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageProcurementVariants::route('/')];
    }
}
