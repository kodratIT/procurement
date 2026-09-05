<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowStepResource\Schemas;

use App\Models\WorkflowStep;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class WorkflowStepInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Workflow & Urutan')
                ->icon(Heroicon::OutlinedQueueList)
                ->schema([
                    TextEntry::make('workflowVersion.workflow.name')
                        ->label('Workflow')
                        ->placeholder('—')
                        ->badge()
                        ->color('gray')
                        ->icon(Heroicon::OutlinedArrowsRightLeft),
                    TextEntry::make('workflowVersion.version_number')
                        ->label('Versi')
                        ->placeholder('—')
                        ->badge()
                        ->color('primary')
                        ->formatStateUsing(fn (mixed $state): string => $state !== null ? 'v'.$state : '—'),
                    TextEntry::make('sequence')
                        ->label('Urutan')
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('name')
                        ->label('Nama Tahap')
                        ->badge()
                        ->color('primary'),
                ])
                ->columns(4)
                ->columnSpanFull(),

            Section::make('Tipe & Mode')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->schema([
                    TextEntry::make('step_type')
                        ->label('Tipe')
                        ->badge()
                        ->formatStateUsing(fn ($state): string => $state instanceof \BackedEnum ? $state->value : (string) $state),
                    TextEntry::make('approval_mode')
                        ->label('Mode')
                        ->badge()
                        ->formatStateUsing(fn ($state): string => $state instanceof \BackedEnum ? $state->value : (string) $state),
                    IconEntry::make('is_required')
                        ->label('Wajib')
                        ->boolean()
                        ->trueIcon(Heroicon::OutlinedCheckCircle)
                        ->falseIcon(Heroicon::OutlinedXCircle),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Resolver & Hak Akses')
                ->icon(Heroicon::OutlinedUserGroup)
                ->schema([
                    TextEntry::make('resolver_type')
                        ->label('Resolver')
                        ->placeholder('— nominal_role')
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('required_permission')
                        ->label('Permission')
                        ->placeholder('—')
                        ->badge()
                        ->color('gray')
                        ->copyable(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('SLA & Konfigurasi')
                ->icon(Heroicon::OutlinedClock)
                ->schema([
                    TextEntry::make('sla_minutes')
                        ->label('SLA')
                        ->placeholder('— Tanpa SLA')
                        ->state(fn (WorkflowStep $record): string => $record->sla_minutes !== null ? $record->sla_minutes.' menit' : '—')
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('escalation_type')
                        ->label('Escalation')
                        ->placeholder('—')
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('conditions_count')
                        ->label('Kondisi')
                        ->counts('conditions')
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('approverMappings_count')
                        ->label('Mappings')
                        ->counts('approverMappings')
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('settings')
                        ->label('Settings (JSON)')
                        ->placeholder('— Kosong')
                        ->state(fn (WorkflowStep $record): string => $record->settings !== null ? json_encode($record->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—')
                        ->columnSpanFull()
                        ->copyable(),
                    TextEntry::make('created_at')
                        ->label('Dibuat')
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),
                    TextEntry::make('updated_at')
                        ->label('Diperbarui')
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),
                ])
                ->columns(4)
                ->columnSpanFull(),

            Section::make('Peringatan Immutable')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->description('Jika versi sudah dipakai PR (isUsed), tahap tidak bisa diedit/hapus.')
                ->schema([
                    TextEntry::make('immutable')
                        ->hiddenLabel()
                        ->state(fn (WorkflowStep $record): string => $record->workflowVersion?->isUsed() ? 'Terkunci — versi sudah dipakai PR.' : 'Masih bisa diedit — versi belum dipakai.')
                        ->badge()
                        ->color(fn (WorkflowStep $record): string => $record->workflowVersion?->isUsed() ? 'danger' : 'success'),
                ])
                ->columnSpanFull()
                ->collapsible(),
        ]);
    }
}
