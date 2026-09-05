<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkflowResource\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class WorkflowForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Workflow')
                ->description('Kode unik workflow dipakai di kategori & resolver. Nama untuk tampilan.')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->schema([
                    TextInput::make('code')
                        ->label('Kode Workflow')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->placeholder('contoh: standard-procurement')
                        ->helperText('Unik, lowercase dengan dash. Dipakai di category.workflow_reference & binding.'),
                    TextInput::make('name')
                        ->label('Nama Workflow')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('contoh: Standard Procurement')
                        ->helperText('Nama tampil di dropdown & preview approval.'),
                    Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(3)
                        ->placeholder('Jelaskan kapan workflow ini dipakai...')
                        ->helperText('Opsional. Untuk dokumentasi admin.')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Status')
                ->description('Aktifkan agar bisa dipilih saat submit PR.')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->schema([
                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Non-aktifkan tanpa menghapus. Workflow non-aktif tidak dipakai resolver.'),
                ])
                ->columnSpanFull(),

            Section::make('Visual Workflow')
                ->description('Pratinjau alur saat ini (read-only). Edit tahap di tab Versions / Workflow Stages.')
                ->icon(Heroicon::OutlinedMap)
                ->schema([
                    View::make('filament.infolists.components.workflow-visual')
                        ->visible(fn (?object $record): bool => $record !== null && $record->exists)
                        ->columnSpanFull(),
                ])
                ->columnSpanFull()
                ->collapsible()
                ->visible(fn (?object $record): bool => $record !== null && $record->exists),
        ]);
    }
}
