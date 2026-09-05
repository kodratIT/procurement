<?php

declare(strict_types=1);

namespace App\Filament\Resources\Budgets;

use App\Filament\Resources\Budgets\Pages\ManageBudgets;
use App\Models\Budget;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\BudgetReservationService;
use App\Services\FeatureModuleService;
use App\Services\MultiOfficeAuthorization;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Unique;

class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'Budgets';

    protected static string|\UnitEnum|null $navigationGroup = 'Organization & Finance';

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = 'budget';

    public static function canAccess(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allows($user, 'ViewAny:Budget'));
    }

    public static function canViewAny(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allows($user, 'ViewAny:Budget'));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['office', 'costCenter']);
        $user = Auth::user();

        return $user instanceof User
            ? app(MultiOfficeAuthorization::class)->scopeForCurrentContext($query, $user, 'ViewAny:Budget')
            : $query->whereKey(0);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('office_id')
                    ->label('Kantor')
                    ->relationship('office', 'name', fn (Builder $query): Builder => $query->where('is_active', true))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('cost_center_id')
                    ->label('Cost center')
                    ->relationship('costCenter', 'name', fn (Builder $query, Get $get): Builder => $query
                        ->where('is_active', true)
                        ->when($get->integer('office_id') > 0, fn (Builder $query): Builder => $query->where('office_id', $get->integer('office_id'))))
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('year')
                    ->numeric()
                    ->integer()
                    ->minValue(2000)
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                            ->where('office_id', $get->integer('office_id'))
                            ->where('cost_center_id', $get->integer('cost_center_id')),
                    ),
                TextInput::make('amount')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->required(),
                Select::make('status')
                    ->options([
                        Budget::STATUS_DRAFT => 'Draft',
                        Budget::STATUS_ACTIVE => 'Active',
                        Budget::STATUS_CLOSED => 'Closed',
                    ])
                    ->required()
                    ->default(Budget::STATUS_ACTIVE),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('office.name')->label('Kantor')->searchable()->sortable(),
                TextColumn::make('costCenter.name')->label('Cost center')->searchable()->sortable(),
                TextColumn::make('year')->sortable(),
                TextColumn::make('allocation')
                    ->label('Alokasi')
                    ->state(fn (Budget $record): string => app(BudgetReservationService::class)->totals($record)['allocation'])
                    ->money('IDR'),
                TextColumn::make('reserved')
                    ->label('Reserved')
                    ->state(fn (Budget $record): string => app(BudgetReservationService::class)->totals($record)['reserved'])
                    ->money('IDR'),
                TextColumn::make('committed')
                    ->label('Committed')
                    ->state(fn (Budget $record): string => app(BudgetReservationService::class)->totals($record)['committed'])
                    ->money('IDR'),
                TextColumn::make('available')
                    ->label('Available')
                    ->state(fn (Budget $record): string => app(BudgetReservationService::class)->totals($record)['available'])
                    ->money('IDR'),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('office')->relationship('office', 'name')->searchable()->preload(),
                SelectFilter::make('cost_center')->relationship('costCenter', 'name')->searchable()->preload(),
                SelectFilter::make('year'),
                SelectFilter::make('status')->options(array_combine(Budget::STATUSES, Budget::STATUSES)),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBudgets::route('/'),
        ];
    }
}
