<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverMappings\Schemas;

use App\Models\WorkflowStep;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ApproverMappingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Workflow & Jenis Resolver')
                ->description('Mulai dari sini. Tentukan step workflow mana dan bagaimana sistem mencari approver.')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->schema([
                    Select::make('workflow_step_id')
                        ->label('Workflow step')
                        ->relationship('workflowStep', 'name', fn (Builder $query): Builder => $query->with('workflowVersion.workflow'))
                        ->getOptionLabelFromRecordUsing(fn (WorkflowStep $record): string => $record->name.' ('.($record->workflowVersion?->workflow?->name ?? '—').')')
                        ->searchable()
                        ->preload()
                        ->placeholder('Kosong = berlaku untuk semua step dengan resolver ini')
                        ->helperText('Kosong = global. Isi = hanya untuk step yang dipilih. Contoh: Purchasing (Operasional) vs Purchasing (Marketing Only).'),
                    Select::make('resolver_type')
                        ->label('Resolver (cara cari approver)')
                        ->options([
                            'role_in_request_office' => 'Role di Kantor Pemohon',
                            'role_in_budget_owner_office' => 'Role di Kantor Pemilik Budget',
                            'specific_user' => 'Orang Tertentu (Specific User)',
                            'department_head' => 'Kepala Departemen',
                            'branch_head' => 'Kepala Cabang',
                            'cost_center_owner' => 'Owner Cost Center',
                            'nominal_role' => 'Nominal Role (dari settings workflow)',
                        ])
                        ->required()
                        ->live()
                        ->helperText('Ini menentukan logika pencarian di WorkflowResolver. Paling umum: Role di Kantor Pemohon.'),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Siapa Approver-nya?')
                ->description('Isi salah satu: Role atau Orang Tertentu. Sistem wajib punya minimal satu.')
                ->icon(Heroicon::OutlinedUserGroup)
                ->schema([
                    Select::make('role_id')
                        ->label('Role / Jabatan')
                        ->relationship('role', 'name', fn (Builder $query): Builder => $query->where('guard_name', 'web')->where('is_active', true))
                        ->searchable()
                        ->preload()
                        ->helperText('Dipakai untuk resolver role_in_* / department_head / branch_head. Dikosongkan jika Specific User.')
                        ->visible(fn (Get $get): bool => $get->string('resolver_type') !== 'specific_user'),
                    Select::make('user_id')
                        ->label('Orang Tertentu')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('Wajib jika Resolver = Specific User. Bisa juga hardcode approver tetap.')
                        ->visible(fn (Get $get): bool => in_array($get->string('resolver_type'), ['specific_user', 'nominal_role'], true) || $get->blank('resolver_type'))
                        ->required(fn (Get $get): bool => $get->string('resolver_type') === 'specific_user'),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Batasan Organisasi (Scope)')
                ->description('Atur di mana mapping ini berlaku. Makin spesifik = prioritas lebih tinggi. Kosong = berlaku untuk semua.')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->schema([
                    Select::make('office_id')
                        ->label('Kantor')
                        ->relationship('office', 'name', fn (Builder $query): Builder => $query->where('is_active', true))
                        ->searchable()
                        ->preload()
                        ->helperText('Filter utama. Kosong = semua kantor.'),
                    Select::make('branch_id')
                        ->label('Cabang')
                        ->relationship('branch', 'name', function (Builder $query, Get $get): Builder {
                            return $query->where('is_active', true)->when($get->integer('office_id', isNullable: true), fn (Builder $query, mixed $officeId): Builder => $query->where('office_id', $officeId));
                        })
                        ->searchable()
                        ->preload()
                        ->helperText('Otomatis terfilter sesuai Kantor di atas.'),
                    Select::make('department_id')
                        ->label('Departemen')
                        ->relationship('department', 'name', function (Builder $query, Get $get): Builder {
                            return $query->where('is_active', true)->when($get->integer('office_id', isNullable: true), fn (Builder $query, mixed $officeId): Builder => $query->where('office_id', $officeId));
                        })
                        ->searchable()
                        ->preload(),
                    Select::make('cost_center_id')
                        ->label('Cost center')
                        ->relationship('costCenter', 'name', function (Builder $query, Get $get): Builder {
                            return $query->where('is_active', true)->when($get->integer('office_id', isNullable: true), fn (Builder $query, mixed $officeId): Builder => $query->where('office_id', $officeId));
                        })
                        ->searchable()
                        ->preload(),
                    Select::make('scope_source')
                        ->label('Sumber kantor acuan')
                        ->options([
                            'request_office' => 'Kantor Pemohon (request_office)',
                            'budget_owner_office' => 'Kantor Pemilik Budget',
                            'request_branch' => 'Cabang Pemohon',
                            'request_department' => 'Departemen Pemohon',
                            'request_cost_center' => 'Cost Center Pemohon',
                            'configured' => 'Konfigurasi (configured)',
                        ])
                        ->required()
                        ->default('request_office')
                        ->helperText('request_office = pakai kantor PR. budget_owner_office = pakai kantor budget (untuk PR lintas kantor).'),
                ])
                ->columns(2)
                ->collapsible()
                ->columnSpanFull(),

            Section::make('Fallback & Prioritas')
                ->description('Apa yang terjadi jika approver utama tidak ada/cuti, dan urutan prioritas jika ada mapping tumpang tindih.')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->schema([
                    Select::make('fallback_type')
                        ->label('Jika approver tidak ditemukan')
                        ->options([
                            'block' => 'Block Submit (gagal ajukan)',
                            'role' => 'Fallback ke Role lain',
                            'user' => 'Fallback ke Orang lain',
                        ])
                        ->required()
                        ->default('block')
                        ->live()
                        ->helperText('block = PR tidak bisa disubmit sampai approver tersedia.'),
                    Select::make('fallback_role_id')
                        ->label('Fallback role')
                        ->relationship('fallbackRole', 'name', fn (Builder $query): Builder => $query->where('guard_name', 'web')->where('is_active', true))
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => $get->string('fallback_type') === 'role')
                        ->required(fn (Get $get): bool => $get->string('fallback_type') === 'role'),
                    Select::make('fallback_user_id')
                        ->label('Fallback user')
                        ->relationship('fallbackUser', 'name')
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => $get->string('fallback_type') === 'user')
                        ->required(fn (Get $get): bool => $get->string('fallback_type') === 'user'),
                    TextInput::make('priority')
                        ->label('Prioritas')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required()
                        ->helperText('Makin besar makin didahulukan. Urutan: priority*100 + jumlah scope spesifik.'),
                    Toggle::make('allow_self_approval')
                        ->label('Izinkan approve diri sendiri?')
                        ->helperText('Jika OFF, pemohon tidak bisa approve PR sendiri.')
                        ->default(false)
                        ->inline(false),
                ])
                ->columns(2)
                ->collapsible()
                ->columnSpanFull(),

            Section::make('Masa Berlaku & Status')
                ->description('Atur kapan mapping aktif dan apakah sedang dipakai.')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->schema([
                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Non-aktifkan tanpa menghapus data.'),
                    DatePicker::make('valid_from')
                        ->label('Berlaku dari')
                        ->required()
                        ->default(now()->toDateString())
                        ->helperText('Kosongkan di DB = selamanya, tapi di form ini wajib isi.'),
                    DatePicker::make('valid_until')
                        ->label('Berlaku sampai')
                        ->afterOrEqual('valid_from')
                        ->helperText('Kosong = tidak ada batas akhir.'),
                    TextInput::make('settings')
                        ->label('Settings (JSON)')
                        ->helperText('Opsional. JSON bebas untuk resolver custom. Kosongkan jika tidak perlu.')
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
