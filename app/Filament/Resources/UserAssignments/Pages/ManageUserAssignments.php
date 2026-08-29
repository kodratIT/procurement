<?php

namespace App\Filament\Resources\UserAssignments\Pages;

use App\Filament\Resources\UserAssignments\UserAssignmentResource;
use App\Models\UserAssignment;
use App\Services\AssignmentBulkService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class ManageUserAssignments extends ManageRecords
{
    protected static string $resource = UserAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkAssign')
                ->label('Assign banyak (bulk)')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->modalHeading('Bulk assign — banyak assignment per user')
                ->modalSubmitActionLabel('Buat assignment')
                ->schema([
                    Select::make('user_id')
                        ->label('User')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),
                    Repeater::make('rows')
                        ->label('Assignments')
                        ->schema([
                            Select::make('office_id')
                                ->label('Kantor')
                                ->relationship('office', 'name', fn (Builder $query) => $query->where('is_active', true)->whereNull('disabled_at'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live(),
                            Select::make('role')
                                ->label('Role')
                                ->options(fn (): array => Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name', 'name')->all())
                                ->searchable()
                                ->required()
                                ->default(UserAssignment::DEFAULT_ROLE),
                            Select::make('branch_id')
                                ->label('Cabang')
                                ->relationship('branch', 'name', fn (Builder $query) => $query->where('is_active', true)->whereNull('disabled_at'))
                                ->searchable()
                                ->preload()
                                ->live(),
                            Select::make('department_id')
                                ->label('Departemen')
                                ->relationship('department', 'name', fn (Builder $query) => $query->where('is_active', true)->whereNull('disabled_at'))
                                ->searchable()
                                ->preload()
                                ->live(),
                            Select::make('cost_center_id')
                                ->label('Cost center')
                                ->relationship('costCenter', 'name', fn (Builder $query) => $query->where('is_active', true)->whereNull('disabled_at'))
                                ->searchable()
                                ->preload(),
                            Toggle::make('is_primary')->label('Primary')->default(false),
                            DatePicker::make('valid_from')->label('Valid from')->required(),
                            DatePicker::make('valid_until')->label('Valid until')->afterOrEqual('valid_from'),
                        ])
                        ->columns(2)
                        ->defaultItems(1)
                        ->addActionLabel('Tambah assignment')
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => isset($state['office_id']) ? 'Assignment' : null),
                ])
                ->action(function (array $data, AssignmentBulkService $service): void {
                    $count = $service->createMany($data['user_id'], $data['rows'] ?? [])->count();

                    Notification::make()
                        ->title("{$count} assignment dibuat")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
