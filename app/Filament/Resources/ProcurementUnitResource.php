<?php

namespace App\Filament\Resources;

use App\Filament\Exports\ProcurementUnitExporter;
use App\Models\ProcurementUnit;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProcurementUnitResource extends Resource
{
    protected static ?string $model = ProcurementUnit::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Units';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'satuan';

    protected static ?string $pluralModelLabel = 'satuan';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->required()->maxLength(30)->unique(ignoreRecord: true),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('symbol')->maxLength(20),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('symbol'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('items_count')->counts('items')->label('Item'),
            ])
            ->filters([
                SelectFilter::make('is_active')->label('Status Aktif')->options(['1' => 'Aktif', '0' => 'Nonaktif']),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('deactivate')
                    ->label('Nonaktifkan')
                    ->requiresConfirmation()
                    ->visible(fn (ProcurementUnit $record): bool => $record->is_active)
                    ->authorize('deactivate')
                    ->action(fn (ProcurementUnit $record): bool => $record->deactivate()),
                Action::make('activate')
                    ->label('Aktifkan')
                    ->visible(fn (ProcurementUnit $record): bool => ! $record->is_active)
                    ->authorize('activate')
                    ->action(fn (ProcurementUnit $record): bool => $record->activate()),
            ])
            ->toolbarActions([
                ExportBulkAction::make()->exporter(ProcurementUnitExporter::class),
            ]);
    }

    public static function canAccess(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allows($user, 'ViewAny:ProcurementUnit'));
    }

    public static function canViewAny(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allows($user, 'ViewAny:ProcurementUnit'));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageProcurementUnits::route('/')];
    }
}
