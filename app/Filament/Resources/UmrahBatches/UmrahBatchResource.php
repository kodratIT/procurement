<?php

declare(strict_types=1);

namespace App\Filament\Resources\UmrahBatches;

use App\Filament\Imports\UmrahBatchImporter;
use App\Filament\Resources\UmrahBatches\Pages\ManageUmrahBatches;
use App\Models\UmrahBatch;
use App\Models\User;
use App\Services\AccessContextService;
use App\Support\ProcurementPermissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Unique;

class UmrahBatchResource extends Resource
{
    protected static ?string $model = UmrahBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Batch Umrah';

    protected static ?string $modelLabel = 'batch umrah';

    protected static ?string $pluralModelLabel = 'batch umrah';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        return $user instanceof User
            ? $query->acrossContexts(ProcurementPermissions::VIEW)
            : $query->whereKey(0);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('office_id')
                    ->label('Kantor')
                    ->options(fn (): array => app(AccessContextService::class)
                        ->allowedOffices()
                        ->where('is_active', true)
                        ->sortBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->default(fn (): ?int => app(AccessContextService::class)->id())
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('office_id', $get->integer('office_id')),
                    ),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('departure_date')->required(),
                DatePicker::make('return_date')->afterOrEqual('departure_date'),
                TextInput::make('capacity')->numeric()->integer()->minValue(1),
                TextInput::make('pilgrim_count')
                    ->label('Jumlah jamaah')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->default(0),
                Select::make('status')
                    ->options(UmrahBatch::STATUSES)
                    ->default(UmrahBatch::STATUS_PLANNED)
                    ->required(),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('office.name')->label('Kantor')->searchable()->sortable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('departure_date')->date()->sortable(),
                TextColumn::make('pilgrim_count')->label('Jamaah')->sortable(),
                TextColumn::make('status')->badge(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('disabled_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('office')->relationship('office', 'name')->searchable()->preload(),
                SelectFilter::make('status')->options(UmrahBatch::STATUSES),
                SelectFilter::make('is_active')->label('Status Aktif')->options(['1' => 'Aktif', '0' => 'Nonaktif']),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('deactivate')
                    ->label('Nonaktifkan')
                    ->requiresConfirmation()
                    ->visible(fn (UmrahBatch $record): bool => $record->is_active)
                    ->authorize('deactivate')
                    ->action(fn (UmrahBatch $record): bool => $record->deactivate()),
                Action::make('activate')
                    ->label('Aktifkan')
                    ->visible(fn (UmrahBatch $record): bool => ! $record->is_active)
                    ->authorize('activate')
                    ->action(fn (UmrahBatch $record): bool => $record->activate()),
            ])
            ->toolbarActions([
                ImportAction::make()
                    ->importer(UmrahBatchImporter::class)
                    ->fileRules([File::types(['csv'])->max(2048)])
                    ->authorize('create')
                    ->options(fn (): array => ['office_id' => app(AccessContextService::class)->id()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUmrahBatches::route('/'),
        ];
    }
}
