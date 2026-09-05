<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pilgrims;

use App\Filament\Imports\PilgrimImporter;
use App\Filament\Resources\Pilgrims\Pages\ManagePilgrims;
use App\Models\Pilgrim;
use App\Models\UmrahBatch;
use App\Models\User;
use App\Services\AccessContextService;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ImportAction;
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
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Unique;

class PilgrimResource extends Resource
{
    protected static ?string $model = Pilgrim::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Pilgrims';

    protected static string|\UnitEnum|null $navigationGroup = 'Umrah Operations';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'jamaah';

    protected static ?string $pluralModelLabel = 'jamaah';

    public static function canAccess(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allowsAcrossAssignments($user, 'ViewAny:Pilgrim'));
    }

    public static function canViewAny(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allowsAcrossAssignments($user, 'ViewAny:Pilgrim'));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        return $user instanceof User
            ? $query->acrossContexts('ViewAny:Pilgrim')
            : $query->whereKey(0);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('umrah_batch_id')
                    ->label('Batch Umrah')
                    ->options(fn (): array => self::activeBatchOptions())
                    ->rules([
                        'required',
                        'integer',
                        Rule::exists('umrah_batches', 'id')->where(
                            fn (QueryBuilder $query): QueryBuilder => $query
                                ->where('office_id', app(AccessContextService::class)->id())
                                ->where('is_active', true)
                                ->whereIn('status', [UmrahBatch::STATUS_PLANNED, UmrahBatch::STATUS_OPEN]),
                        ),
                    ])
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->label('Nama lengkap')
                    ->required()
                    ->maxLength(255),
                TextInput::make('passport_no')
                    ->label('Nomor paspor')
                    ->required()
                    ->maxLength(50)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                            ->where('umrah_batch_id', $get->integer('umrah_batch_id')),
                    ),
                TextInput::make('phone')
                    ->label('Nomor telepon')
                    ->maxLength(30),
                Select::make('status')
                    ->options(Pilgrim::STATUSES)
                    ->default(Pilgrim::STATUS_REGISTERED)
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
                TextColumn::make('batch.name')->label('Batch')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('passport_no')->label('Nomor paspor')->searchable(),
                TextColumn::make('phone')->toggleable(),
                TextColumn::make('status')->badge(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('disabled_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('umrah_batch_id')->label('Batch')->options(fn (): array => self::activeBatchOptions()),
                SelectFilter::make('status')->options(Pilgrim::STATUSES),
                SelectFilter::make('is_active')->label('Status Aktif')->options(['1' => 'Aktif', '0' => 'Nonaktif']),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('deactivate')
                    ->label('Nonaktifkan')
                    ->requiresConfirmation()
                    ->visible(fn (Pilgrim $record): bool => $record->is_active)
                    ->authorize('deactivate')
                    ->action(fn (Pilgrim $record): bool => $record->deactivate()),
                Action::make('activate')
                    ->label('Aktifkan')
                    ->visible(fn (Pilgrim $record): bool => ! $record->is_active)
                    ->authorize('activate')
                    ->action(fn (Pilgrim $record): bool => $record->activate()),
            ])
            ->toolbarActions([
                ImportAction::make()
                    ->importer(PilgrimImporter::class)
                    ->fileRules([File::types(['csv'])->max(2048)])
                    ->authorize('create')
                    ->options(fn (): array => ['office_id' => app(AccessContextService::class)->id()]),
            ]);
    }

    /** @return array<int|string, string> */
    private static function activeBatchOptions(): array
    {
        $officeId = app(AccessContextService::class)->id();

        if ($officeId === null) {
            return [];
        }

        return UmrahBatch::query()
            ->withoutGlobalScopes()
            ->where('office_id', $officeId)
            ->availableForNewPilgrims()
            ->orderBy('departure_date')
            ->orderBy('id')
            ->pluck('name', 'id')
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePilgrims::route('/'),
        ];
    }
}
