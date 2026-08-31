<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverMappings\Schemas;

use App\Models\ApproverMapping;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ApproverMappingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('workflow_step_id')
                ->label('Workflow step')
                ->relationship('workflowStep', 'name')
                ->searchable()
                ->preload(),
            Select::make('resolver_type')
                ->label('Resolver')
                ->options(array_combine(ApproverMapping::RESOLVER_TYPES, array_map(ucwords(...), ApproverMapping::RESOLVER_TYPES)))
                ->required()
                ->live(),
            Select::make('role_id')
                ->label('Role')
                ->relationship('role', 'name', fn (Builder $query): Builder => $query->where('guard_name', 'web')->where('is_active', true))
                ->searchable()
                ->preload(),
            Select::make('user_id')
                ->label('Specific user')
                ->relationship('user', 'name')
                ->searchable()
                ->preload(),
            Select::make('office_id')
                ->label('Kantor')
                ->relationship('office', 'name', fn (Builder $query): Builder => $query->where('is_active', true))
                ->searchable()
                ->preload(),
            Select::make('branch_id')
                ->label('Cabang')
                ->relationship('branch', 'name', function (Builder $query, Get $get): Builder {
                    return $query->where('is_active', true)->when($get->get('office_id'), fn (Builder $query, mixed $officeId): Builder => $query->where('office_id', $officeId));
                })
                ->searchable()
                ->preload(),
            Select::make('department_id')
                ->label('Departemen')
                ->relationship('department', 'name', function (Builder $query, Get $get): Builder {
                    return $query->where('is_active', true)->when($get->get('office_id'), fn (Builder $query, mixed $officeId): Builder => $query->where('office_id', $officeId));
                })
                ->searchable()
                ->preload(),
            Select::make('cost_center_id')
                ->label('Cost center')
                ->relationship('costCenter', 'name', function (Builder $query, Get $get): Builder {
                    return $query->where('is_active', true)->when($get->get('office_id'), fn (Builder $query, mixed $officeId): Builder => $query->where('office_id', $officeId));
                })
                ->searchable()
                ->preload(),
            Select::make('scope_source')
                ->label('Scope source')
                ->options(array_combine(ApproverMapping::SCOPE_SOURCES, array_map(ucwords(...), ApproverMapping::SCOPE_SOURCES)))
                ->required()
                ->default('request_office'),
            Select::make('fallback_type')
                ->label('Fallback')
                ->options(array_combine(ApproverMapping::FALLBACK_TYPES, ['Block submit', 'Fallback role', 'Fallback user']))
                ->required()
                ->default('block')
                ->live(),
            Select::make('fallback_role_id')
                ->label('Fallback role')
                ->relationship('fallbackRole', 'name', fn (Builder $query): Builder => $query->where('guard_name', 'web')->where('is_active', true))
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get->get('fallback_type') === 'role'),
            Select::make('fallback_user_id')
                ->label('Fallback user')
                ->relationship('fallbackUser', 'name')
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get->get('fallback_type') === 'user'),
            TextInput::make('priority')->numeric()->minValue(0)->default(0)->required(),
            Toggle::make('allow_self_approval')->label('Allow self-approval')->default(false),
            Toggle::make('is_active')->label('Active')->default(true),
            DatePicker::make('valid_from')->label('Valid from')->required()->default(now()->toDateString()),
            DatePicker::make('valid_until')->label('Valid until')->afterOrEqual('valid_from'),
            TextInput::make('settings')->json()->columnSpanFull(),
        ])->columns(2);
    }
}
