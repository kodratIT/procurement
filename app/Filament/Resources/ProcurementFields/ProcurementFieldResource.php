<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcurementFields;

use App\Enums\ProcurementFieldType;
use App\Filament\Resources\ProcurementFields\Pages\ManageProcurementFields;
use App\Models\ProcurementField;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class ProcurementFieldResource extends Resource
{
    protected static ?string $model = ProcurementField::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Custom Fields';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = 'field dinamis';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('key')
                    ->helperText('Gunakan lowercase dan underscore, misalnya room_type.')
                    ->required()
                    ->maxLength(100)
                    ->regex('/^[a-z][a-z0-9_]*$/')
                    ->unique(ignoreRecord: true),
                TextInput::make('label')
                    ->required()
                    ->maxLength(255),
                Select::make('field_type')
                    ->label('Tipe')
                    ->options(ProcurementFieldType::options())
                    ->enum(ProcurementFieldType::class)
                    ->required()
                    ->live(),
                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->required()
                    ->default(0),
                Toggle::make('is_required')
                    ->label('Wajib')
                    ->default(false),
                KeyValue::make('options')
                    ->label('Opsi')
                    ->keyLabel('Nilai')
                    ->valueLabel('Label')
                    ->helperText('Isi untuk dropdown, radio, relasi, atau varian.'),
                TextInput::make('default_value')
                    ->label('Nilai default'),
                TextInput::make('min_value')
                    ->label('Nilai minimum')
                    ->maxLength(100),
                TextInput::make('max_value')
                    ->label('Nilai maksimum')
                    ->maxLength(100),
                Repeater::make('visibility_conditions')
                    ->label('Kondisi tampil')
                    ->schema([
                        TextInput::make('field')->label('Field sumber')->required(),
                        Select::make('operator')
                            ->options([
                                'equals' => 'Sama dengan',
                                'not_equals' => 'Tidak sama dengan',
                                'in' => 'Termasuk',
                                'not_in' => 'Tidak termasuk',
                                'contains' => 'Mengandung',
                                'is_empty' => 'Kosong',
                                'is_not_empty' => 'Tidak kosong',
                            ])
                            ->required(),
                        TextInput::make('value')->label('Nilai pembanding'),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->collapsible(),
                Select::make('editable_stage')
                    ->label('Tahap dapat diubah')
                    ->options([
                        ProcurementField::EDITABLE_STAGE_DRAFT => 'Draft',
                        ProcurementField::EDITABLE_STAGE_REVIEW => 'Review pengadaan',
                        ProcurementField::EDITABLE_STAGE_APPROVAL => 'Approval',
                    ])
                    ->required()
                    ->default(ProcurementField::EDITABLE_STAGE_DRAFT),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('category.name')->label('Kategori'),
                TextEntry::make('key'),
                TextEntry::make('label'),
                TextEntry::make('field_type')
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof ProcurementFieldType
                        ? $state->label()
                        : (ProcurementFieldType::tryFrom((string) $state)?->label() ?? (string) $state)),
                TextEntry::make('sort_order')->label('Urutan'),
                TextEntry::make('version'),
                TextEntry::make('editable_stage')->label('Tahap dapat diubah'),
                IconEntry::make('is_required')->boolean()->label('Wajib'),
                IconEntry::make('is_active')->boolean()->label('Aktif'),
                TextEntry::make('options')->state(fn (ProcurementField $record): string => json_encode($record->options, JSON_THROW_ON_ERROR)),
                TextEntry::make('visibility_conditions')
                    ->state(fn (ProcurementField $record): string => json_encode($record->visibility_conditions, JSON_THROW_ON_ERROR)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('category.name')->label('Kategori')->sortable()->searchable(),
                TextColumn::make('sort_order')->label('Urutan')->sortable(),
                TextColumn::make('key')->searchable(),
                TextColumn::make('label')->searchable(),
                TextColumn::make('field_type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof ProcurementFieldType
                        ? $state->label()
                        : (ProcurementFieldType::tryFrom((string) $state)?->label() ?? (string) $state)),
                IconColumn::make('is_required')->label('Wajib')->boolean(),
                TextColumn::make('editable_stage')->label('Tahap'),
                TextColumn::make('version')->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category_id')->relationship('category', 'name'),
                SelectFilter::make('field_type')->options(ProcurementFieldType::options()),
                SelectFilter::make('is_active')->options(['1' => 'Aktif', '0' => 'Nonaktif']),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->modalHeading(fn (ProcurementField $record): string => 'Preview: '.$record->label)
                    ->modalContent(fn (ProcurementField $record): View => view(
                        'filament.procurement-field-preview',
                        ['field' => $record],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                EditAction::make(),
                DeleteAction::make(),
                Action::make('deactivate')
                    ->label('Nonaktifkan')
                    ->requiresConfirmation()
                    ->visible(fn (ProcurementField $record): bool => $record->is_active)
                    ->authorize('deactivate')
                    ->action(fn (ProcurementField $record): bool => $record->deactivate()),
                Action::make('activate')
                    ->label('Aktifkan')
                    ->visible(fn (ProcurementField $record): bool => ! $record->is_active)
                    ->authorize('activate')
                    ->action(fn (ProcurementField $record): bool => $record->activate()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canAccess(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allows($user, 'ViewAny:ProcurementField'));
    }

    public static function canViewAny(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allows($user, 'ViewAny:ProcurementField'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProcurementFields::route('/'),
        ];
    }
}
