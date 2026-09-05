<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowStepResource\Schemas;

use App\Enums\WorkflowApprovalMode;
use App\Enums\WorkflowStepType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class WorkflowStepForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Workflow & Urutan')
                ->description('Tentukan versi workflow induk dan posisi tahap (1 = pertama).')
                ->icon(Heroicon::OutlinedQueueList)
                ->schema([
                    Select::make('workflow_version_id')
                        ->label('Workflow Version')
                        ->relationship('workflowVersion', 'id', fn (Builder $query): Builder => $query->with('workflow'))
                        ->getOptionLabelFromRecordUsing(fn ($record): string => ($record->workflow?->name ?? '—').' — v'.$record->version_number.' ('.$record->status->value.')')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Pilih versi draft yang akan ditambah tahap.'),
                    TextInput::make('sequence')
                        ->label('Urutan')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->placeholder('1')
                        ->helperText('Harus berurutan 1..n tanpa lubang per versi.'),
                    TextInput::make('name')
                        ->label('Nama Tahap')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('contoh: Procurement Review')
                        ->helperText('Nama tampil di timeline & inbox.'),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Tipe & Mode Approval')
                ->description('Atur bagaimana tahap diproses.')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->schema([
                    Select::make('step_type')
                        ->label('Tipe Tahap')
                        ->options(WorkflowStepType::options())
                        ->enum(WorkflowStepType::class)
                        ->required()
                        ->helperText('Review / Approval / Informational.'),
                    Select::make('approval_mode')
                        ->label('Mode Approval')
                        ->options(WorkflowApprovalMode::options())
                        ->enum(WorkflowApprovalMode::class)
                        ->required()
                        ->default(WorkflowApprovalMode::Sequential->value)
                        ->helperText('Sequential paling umum.'),
                    Toggle::make('is_required')
                        ->label('Wajib?')
                        ->default(true)
                        ->inline(false)
                        ->helperText('OFF = boleh skip jika kondisi tidak terpenuhi.'),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Resolver & Hak Akses')
                ->description('Cara sistem mencari approver untuk tahap ini.')
                ->icon(Heroicon::OutlinedUserGroup)
                ->schema([
                    TextInput::make('resolver_type')
                        ->label('Resolver Type')
                        ->maxLength(50)
                        ->placeholder('role_in_request_office')
                        ->helperText('Isi: role_in_request_office, specific_user, department_head, dll. Kosong = nominal_role.'),
                    TextInput::make('required_permission')
                        ->label('Required Permission')
                        ->maxLength(100)
                        ->placeholder('procurement.approve')
                        ->helperText('Permission yang harus dimiliki assignment approver.'),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('SLA & Lainnya')
                ->description('Opsional. Atur batas waktu & konfigurasi tambahan.')
                ->icon(Heroicon::OutlinedClock)
                ->schema([
                    TextInput::make('sla_minutes')
                        ->label('SLA (menit)')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('kosong = tanpa SLA')
                        ->helperText('Dipakai scheduler untuk escalate.'),
                    TextInput::make('escalation_type')
                        ->label('Escalation Type')
                        ->maxLength(30)
                        ->placeholder('kosong')
                        ->helperText('Opsional.'),
                    TextInput::make('settings')
                        ->label('Settings (JSON)')
                        ->helperText('Opsional. JSON untuk resolver custom. Kosongkan jika tidak perlu.')
                        ->json()
                        ->columnSpanFull(),
                ])
                ->columns(3)
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),
        ]);
    }
}
