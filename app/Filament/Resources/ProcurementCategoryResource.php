<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ProcurementCategoryType;
use App\Filament\Exports\ProcurementCategoryExporter;
use App\Models\ProcurementCategory;
use App\Models\User;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementCategoryConfiguration;
use App\Support\ProcurementPermissions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Illuminate\Support\Facades\Auth;

class ProcurementCategoryResource extends Resource
{
    protected static ?string $model = ProcurementCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Kategori';

    protected static ?string $modelLabel = 'kategori';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        return $user instanceof User
            ? app(MultiOfficeAuthorization::class)->scopeForUser(
                $query,
                $user,
                ProcurementPermissions::MANAGE_MASTER_DATA,
            )
            : $query->whereKey(0);
    }

    public static function form(Schema $schema): Schema
    {
        $flagToggles = [];
        foreach (ProcurementCategoryConfiguration::flagLabels() as $field => $label) {
            $flagToggles[] = Toggle::make($field)->label($label);
        }

        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Tipe')
                    ->options(ProcurementCategoryType::options())
                    ->enum(ProcurementCategoryType::class)
                    ->required(),
                Textarea::make('description')->columnSpanFull(),
                ...$flagToggles,
                TextInput::make('workflow_reference')
                    ->label('Referensi workflow')
                    ->maxLength(100),
                TextInput::make('number_template')
                    ->label('Template nomor')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        $flagColumns = [];
        foreach (ProcurementCategoryConfiguration::flagLabels() as $field => $label) {
            $flagColumns[] = IconColumn::make($field)
                ->boolean()
                ->label(str_replace('Wajib ', '', $label));
        }

        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof ProcurementCategoryType
                        ? $state->label()
                        : (ProcurementCategoryType::tryFrom((string) $state)?->label() ?? (string) $state)),
                ...$flagColumns,
                TextColumn::make('workflow_reference')->label('Workflow')->toggleable(),
                TextColumn::make('number_template')->label('Template nomor')->toggleable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('disabled_at')->dateTime()->sortable()->toggleable(),
                TextColumn::make('items_count')->counts('items')->label('Items'),
            ])
            ->filters([
                SelectFilter::make('is_active')->options(['1' => 'Aktif', '0' => 'Nonaktif']),
                SelectFilter::make('type')->options(ProcurementCategoryType::options()),
            ])
            ->recordActions([
                DeleteAction::make(),
                EditAction::make(),
                Action::make('deactivate')
                    ->label('Nonaktifkan')
                    ->requiresConfirmation()
                    ->visible(fn (ProcurementCategory $record): bool => $record->is_active)
                    ->authorize('deactivate')
                    ->action(fn (ProcurementCategory $record): bool => $record->deactivate()),
                Action::make('activate')
                    ->label('Aktifkan')
                    ->visible(fn (ProcurementCategory $record): bool => ! $record->is_active)
                    ->authorize('activate')
                    ->action(fn (ProcurementCategory $record): bool => $record->activate()),
            ])
            ->toolbarActions([
                ExportBulkAction::make()
                    ->exporter(ProcurementCategoryExporter::class),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProcurementCategories::route('/'),
        ];
    }
}
