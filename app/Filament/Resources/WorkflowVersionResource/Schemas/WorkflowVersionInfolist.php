<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowVersionResource\Schemas;

use App\Enums\WorkflowVersionStatus;
use App\Models\WorkflowVersion;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class WorkflowVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Workflow & Versi')
                ->icon(Heroicon::OutlinedQueueList)
                ->schema([
                    TextEntry::make('workflow.name')
                        ->label('Workflow')
                        ->badge()
                        ->color('gray')
                        ->icon(Heroicon::OutlinedArrowsRightLeft),
                    TextEntry::make('workflow.code')
                        ->label('Kode Workflow')
                        ->badge()
                        ->color('gray')
                        ->copyable(),
                    TextEntry::make('version_number')
                        ->label('Versi')
                        ->badge()
                        ->color('primary')
                        ->formatStateUsing(fn (mixed $state): string => 'v'.$state),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->colors([
                            'gray' => fn (mixed $state): bool => ($state instanceof \BackedEnum ? $state->value : (string) $state) === 'draft',
                            'success' => fn (mixed $state): bool => ($state instanceof \BackedEnum ? $state->value : (string) $state) === 'active',
                            'warning' => fn (mixed $state): bool => ($state instanceof \BackedEnum ? $state->value : (string) $state) === 'retired',
                        ])
                        ->formatStateUsing(fn ($state): string => $state instanceof WorkflowVersionStatus ? $state->value : (string) $state),
                ])
                ->columns(4)
                ->columnSpanFull(),

            Section::make('Periode & Pemakaian')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->schema([
                    TextEntry::make('effective_from')
                        ->label('Efektif Dari')
                        ->dateTime('d M Y H:i')
                        ->placeholder('— Segera'),
                    TextEntry::make('effective_until')
                        ->label('Efektif Sampai')
                        ->dateTime('d M Y H:i')
                        ->placeholder('∞ Selamanya'),
                    TextEntry::make('isUsed')
                        ->label('Dipakai PR?')
                        ->state(fn (WorkflowVersion $record): string => $record->isUsed() ? 'Ya — immutable' : 'Belum — masih bisa edit')
                        ->badge()
                        ->color(fn (WorkflowVersion $record): string => $record->isUsed() ? 'danger' : 'success')
                        ->icon(fn (WorkflowVersion $record): Heroicon|string => $record->isUsed() ? Heroicon::OutlinedLockClosed : Heroicon::OutlinedLockOpen),
                    TextEntry::make('steps_count')
                        ->label('Jumlah Tahap')
                        ->counts('steps')
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('activated_at')
                        ->label('Diaktifkan')
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),
                    TextEntry::make('retired_at')
                        ->label('Dipensiunkan')
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),
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

            Section::make('Bantuan')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->description('Versi active dipakai resolver. Steps harus urut 1..n tanpa lubang.')
                ->schema([
                    TextEntry::make('hint')
                        ->hiddenLabel()
                        ->state('Gunakan Steps relation untuk tambah tahap. Saat activate sistem validasi urutan & kondisi.')
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),
        ]);
    }
}
