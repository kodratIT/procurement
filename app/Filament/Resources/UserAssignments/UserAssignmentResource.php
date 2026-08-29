<?php

namespace App\Filament\Resources\UserAssignments;

use App\Filament\Exports\UserAssignmentExporter;
use App\Filament\Resources\UserAssignments\Pages\ManageUserAssignments;
use App\Models\UserAssignment;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserAssignmentResource extends Resource
{
    protected static ?string $model = UserAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'User Assignments';

    protected static ?string $modelLabel = 'user assignment';

    protected static ?string $pluralModelLabel = 'user assignments';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('User')->relationship('user', 'name')->searchable()->preload()->required(),
            Select::make('office_id')->label('Kantor')->relationship('office', 'name', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload()->required()->live(),
            Select::make('role')->label('Role')->options(fn (): array => Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name', 'name')->all())->searchable()->required(),
            Select::make('branch_id')->label('Cabang')->relationship('branch', 'name', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload(),
            Select::make('department_id')->label('Departemen')->relationship('department', 'name', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload(),
            Select::make('cost_center_id')->label('Cost center')->relationship('costCenter', 'name', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload(),
            Toggle::make('is_primary')->label('Primary assignment')->default(false),
            DatePicker::make('valid_from')->label('Valid from')->required(),
            DatePicker::make('valid_until')->label('Valid until')->afterOrEqual('valid_from'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.name')->label('User')->searchable()->sortable(),
            TextColumn::make('office.name')->label('Kantor')->searchable()->sortable(),
            TextColumn::make('role')->badge()->searchable(),
            TextColumn::make('branch.name')->label('Cabang')->toggleable(),
            TextColumn::make('department.name')->label('Departemen')->toggleable(),
            TextColumn::make('costCenter.name')->label('Cost center')->toggleable(),
            IconColumn::make('is_primary')->label('Primary')->boolean(),
            IconColumn::make('is_active')->label('Active')->boolean(),
            TextColumn::make('valid_from')->date()->sortable(),
            TextColumn::make('valid_until')->date()->sortable()->toggleable(),
        ])->filters([
            SelectFilter::make('user')->relationship('user', 'name')->searchable()->preload(),
            SelectFilter::make('office')->relationship('office', 'name')->searchable()->preload(),
            SelectFilter::make('role')->options(fn (): array => UserAssignment::query()->whereNotNull('role')->distinct()->orderBy('role')->pluck('role', 'role')->all()),
            SelectFilter::make('is_primary')->options(['1' => 'Primary', '0' => 'Not primary']),
            SelectFilter::make('is_active')->options(['1' => 'Active', '0' => 'Inactive']),
        ])->recordActions([EditAction::make(), DeleteAction::make()])->toolbarActions([
            ExportBulkAction::make()->exporter(UserAssignmentExporter::class),
            BulkActionGroup::make([
                BulkAction::make('setPrimary')->label('Set primary')->icon(Heroicon::OutlinedStar)->requiresConfirmation()->action(function (Collection $records): void {
                    DB::transaction(function () use ($records): void {
                        foreach ($records as $assignment) {
                            UserAssignment::query()->where('user_id', $assignment->user_id)->update(['is_primary' => false]);
                            $assignment->update(['is_primary' => true]);
                        }
                    });
                }),
                BulkAction::make('activate')->label('Activate')->action(fn (Collection $records) => $records->each->update(['is_active' => true])),
                BulkAction::make('deactivate')->label('Deactivate')->requiresConfirmation()->action(fn (Collection $records) => $records->each->update(['is_active' => false])),
                DeleteBulkAction::make(),
            ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'office', 'branch', 'department', 'costCenter']);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUserAssignments::route('/')];
    }
}
