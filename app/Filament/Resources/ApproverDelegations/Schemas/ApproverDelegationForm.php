<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverDelegations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ApproverDelegationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Penugasan Delegasi')
                ->description('Tentukan siapa yang didelegasikan dan siapa penggantinya. Delegasi hanya berlaku jika approver asli non-aktif.')
                ->icon(Heroicon::OutlinedArrowPath)
                ->schema([
                    Select::make('delegator_id')
                        ->label('Original approver')
                        ->relationship('delegator', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Approver yang sedang cuti / tidak aktif. Delegasi baru dipakai jika user ini non-aktif.'),
                    Select::make('delegate_id')
                        ->label('Delegate (pengganti)')
                        ->relationship('delegate', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('User pengganti yang akan menerima task approval. Tidak boleh sama dengan original.'),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Masa Berlaku & Status')
                ->description('Atur kapan delegasi aktif dan apakah sedang dipakai.')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->schema([
                    DatePicker::make('valid_from')
                        ->label('Berlaku dari')
                        ->required()
                        ->default(now()->toDateString())
                        ->helperText('Tanggal mulai delegasi berlaku.'),
                    DatePicker::make('valid_until')
                        ->label('Berlaku sampai')
                        ->required()
                        ->afterOrEqual('valid_from')
                        ->helperText('Tanggal selesai delegasi. Harus >= mulai.'),
                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Non-aktifkan tanpa menghapus data.'),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Alasan Delegasi')
                ->description('Wajib isi alasan untuk audit log workflow.')
                ->icon(Heroicon::OutlinedDocumentText)
                ->schema([
                    Textarea::make('reason')
                        ->label('Reason / Alasan')
                        ->required()
                        ->minLength(3)
                        ->rows(3)
                        ->placeholder('Contoh: Cuti tahunan 1-10 Sep, dinas luar kota, sakit...')
                        ->helperText('Minimal 3 karakter. Akan tercatat di activity log.')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ]);
    }
}
