<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowResource\Schemas;

use App\Models\Workflow;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class WorkflowInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Workflow')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->schema([
                    TextEntry::make('code')
                        ->label('Kode')
                        ->badge()
                        ->color('gray')
                        ->copyable()
                        ->icon(Heroicon::OutlinedClipboardDocumentList),
                    TextEntry::make('name')
                        ->label('Nama')
                        ->badge()
                        ->color('primary'),
                    TextEntry::make('description')
                        ->label('Deskripsi')
                        ->placeholder('— Tidak ada deskripsi')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Status & Versi')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->schema([
                    IconEntry::make('is_active')
                        ->label('Aktif')
                        ->boolean(),
                    TextEntry::make('active_version')
                        ->label('Versi Aktif')
                        ->state(fn (Workflow $record): string => $record->activeVersion()?->version_number ? 'v'.$record->activeVersion()->version_number.' ('.$record->activeVersion()->status->value.')' : '— Belum ada')
                        ->badge()
                        ->color(fn (Workflow $record): string => $record->activeVersion() ? 'success' : 'gray'),
                    TextEntry::make('versions_count')
                        ->label('Total Versi')
                        ->counts('versions')
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('bindings_count')
                        ->label('Total Binding')
                        ->counts('bindings')
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('created_at')
                        ->label('Dibuat')
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),
                    TextEntry::make('updated_at')
                        ->label('Diperbarui')
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Bantuan')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->description('Workflow dipakai via category.workflow_reference fallback standard-procurement. Versi aktif dipilih oleh WorkflowResolver.')
                ->schema([
                    TextEntry::make('hint')
                        ->hiddenLabel()
                        ->state('Gunakan Manage → Versions untuk menambah versi, dan Bindings untuk atur prioritas kategori/amount.')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull()
                ->collapsible()
                ->collapsed(),
        ]);
    }
}
