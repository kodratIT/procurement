<?php

namespace App\Filament\Resources\Departments;

use App\Filament\Resources\Departments\Pages\ManageDepartments;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
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
use Illuminate\Validation\Rules\Unique;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Departments';

    protected static string|\UnitEnum|null $navigationGroup = 'Organization & Finance';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'departemen';

    public static function canAccess(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allowsAcrossAssignments($user, 'ViewAny:Department'));
    }

    public static function canViewAny(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allowsAcrossAssignments($user, 'ViewAny:Department'));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        return $user instanceof User
            ? $query->acrossContexts('ViewAny:Department')
            : $query->whereKey(0);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('office_id')
                    ->label('Kantor')
                    ->relationship('office', 'name', fn (Builder $query) => $query->where('is_active', true))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Select::make('branch_id')
                    ->label('Cabang')
                    ->options(fn (Get $get): array => Branch::query()
                        ->where('office_id', $get->integer('office_id'))
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),
                TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('office_id', $get->integer('office_id')),
                    ),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('office_id', $get->integer('office_id')),
                    ),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('office.name')->label('Kantor')->searchable()->sortable(),
                TextColumn::make('branch.name')->label('Cabang')->searchable()->sortable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('disabled_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('office')->relationship('office', 'name')->searchable()->preload(),
                SelectFilter::make('is_active')->options(['1' => 'Aktif', '0' => 'Nonaktif']),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('deactivate')
                    ->label('Nonaktifkan')
                    ->requiresConfirmation()
                    ->visible(fn (Department $record): bool => $record->is_active)
                    ->authorize('deactivate')
                    ->action(function (Department $record): void {
                        $record->deactivate();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDepartments::route('/'),
        ];
    }
}
