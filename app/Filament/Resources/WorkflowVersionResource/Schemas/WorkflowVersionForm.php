<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowVersionResource\Schemas;

use App\Enums\WorkflowVersionStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class WorkflowVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Workflow & Versi')
                ->description('Pilih workflow induk dan nomor versi. Status draft = belum aktif.')
                ->icon(Heroicon::OutlinedQueueList)
                ->schema([
                    Select::make('workflow_id')
                        ->label('Workflow')
                        ->relationship('workflow', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Induk workflow. Kode workflow akan dipakai binding.'),
                    TextInput::make('version_number')
                        ->label('Nomor Versi')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->placeholder('1')
                        ->helperText('Berurutan per workflow. Saat activate, versi lain otomatis pensiun.'),
                    Select::make('status')
                        ->label('Status')
                        ->options(WorkflowVersionStatus::options())
                        ->enum(WorkflowVersionStatus::class)
                        ->required()
                        ->default(WorkflowVersionStatus::Draft->value)
                        ->helperText('Draft → Active (pakai action Aktifkan) → Retired. Versi yang sudah dipakai PR tidak bisa diedit.'),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Periode Efektif')
                ->description('Opsional. Batasi kapan versi berlaku.')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->schema([
                    DateTimePicker::make('effective_from')
                        ->label('Efektif dari')
                        ->helperText('Kosong = segera saat activate.'),
                    DateTimePicker::make('effective_until')
                        ->label('Efektif sampai')
                        ->after('effective_from')
                        ->helperText('Kosong = selamanya. Harus > dari.'),
                ])
                ->columns(2)
                ->collapsible()
                ->columnSpanFull(),
        ]);
    }
}
