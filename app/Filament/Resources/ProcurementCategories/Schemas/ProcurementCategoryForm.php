<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcurementCategories\Schemas;

use App\Enums\ProcurementCategoryType;
use App\Models\ProcurementCategory;
use App\Models\Workflow;
use App\Support\ProcurementCategoryConfiguration;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class ProcurementCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Dasar')
                ->description('Identitas kategori yang tampil di seluruh proses procurement. Kode harus unik.')
                ->icon(Heroicon::OutlinedTag)
                ->schema([
                    TextInput::make('code')
                        ->label('Kode Kategori')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->placeholder('Contoh: CAT-ATK, JASA-KONSULT')
                        ->helperText('Unik, max 50 karakter. Dipakai di referensi workflow & binding.'),
                    TextInput::make('name')
                        ->label('Nama Kategori')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Contoh: Alat Tulis Kantor')
                        ->helperText('Nama yang tampil di dropdown PR & laporan.'),
                    Select::make('type')
                        ->label('Tipe')
                        ->options(ProcurementCategoryType::options())
                        ->enum(ProcurementCategoryType::class)
                        ->required()
                        ->helperText('Barang / Jasa / Barang dan Jasa. Menentukan validasi default.'),
                    Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(3)
                        ->placeholder('Jelaskan kegunaan kategori ini...')
                        ->helperText('Opsional. Tampil sebagai tooltip di form PR.')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Persyaratan Proses')
                ->description('Centang apa yang wajib diisi saat buat PR untuk kategori ini. Makin banyak wajib = makin ketat validasi.')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->schema([
                    Toggle::make('requires_batch')
                        ->label(ProcurementCategoryConfiguration::flagLabels()['requires_batch'])
                        ->helperText('Wajib pilih Batch Keberangkatan (Umrah).')
                        ->inline(false),
                    Toggle::make('requires_jamaah')
                        ->label(ProcurementCategoryConfiguration::flagLabels()['requires_jamaah'])
                        ->helperText('Wajib terkait data Jamaah / Pilgrim.')
                        ->inline(false),
                    Toggle::make('requires_vendor')
                        ->label(ProcurementCategoryConfiguration::flagLabels()['requires_vendor'])
                        ->helperText('PR harus pilih vendor.')
                        ->inline(false),
                    Toggle::make('requires_quotation')
                        ->label(ProcurementCategoryConfiguration::flagLabels()['requires_quotation'])
                        ->helperText('Wajib upload / input quotation.')
                        ->inline(false),
                    Toggle::make('requires_recommendation_reason')
                        ->label(ProcurementCategoryConfiguration::flagLabels()['requires_recommendation_reason'])
                        ->helperText('Wajib isi alasan rekomendasi vendor.')
                        ->inline(false),
                    Toggle::make('requires_recommendation_evidence')
                        ->label(ProcurementCategoryConfiguration::flagLabels()['requires_recommendation_evidence'])
                        ->helperText('Wajib upload bukti rekomendasi.')
                        ->inline(false),
                    Toggle::make('requires_receipt')
                        ->label(ProcurementCategoryConfiguration::flagLabels()['requires_receipt'])
                        ->helperText('Wajib proses penerimaan barang/jasa.')
                        ->inline(false),
                    Toggle::make('requires_invoice')
                        ->label(ProcurementCategoryConfiguration::flagLabels()['requires_invoice'])
                        ->helperText('Wajib lampirkan invoice.')
                        ->inline(false),
                    Toggle::make('requires_po')
                        ->label(ProcurementCategoryConfiguration::flagLabels()['requires_po'])
                        ->helperText('Wajib terbitkan Purchase Order.')
                        ->inline(false),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Workflow & Penomoran')
                ->description('Atur workflow approval mana yang dipakai dan template nomor PR untuk kategori ini. Data diambil langsung dari database.')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->schema([
                    Select::make('workflow_reference')
                        ->label('Referensi Workflow')
                        ->options(function (): array {
                            return Workflow::query()
                                ->where('is_active', true)
                                ->orderBy('code')
                                ->pluck('name', 'code')
                                ->mapWithKeys(fn (string $name, string $code): array => [$code => $code.' — '.$name])
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->placeholder('Kosong = fallback standard-procurement')
                        ->helperText(function (): string {
                            $count = Workflow::query()->where('is_active', true)->count();

                            return 'Pilih workflow aktif dari database ('.$count.' tersedia). Kosong = pakai workflow default standard-procurement.';
                        }),
                    TextInput::make('number_template')
                        ->label('Template Nomor')
                        ->maxLength(255)
                        ->datalist(function (): array {
                            $existing = ProcurementCategory::query()
                                ->whereNotNull('number_template')
                                ->where('number_template', '!=', '')
                                ->distinct()
                                ->pluck('number_template', 'number_template')
                                ->all();

                            // Tambah pola umum bila belum ada di DB
                            $defaults = [
                                'PR-{YYYY}-{SEQ}' => 'PR-{YYYY}-{SEQ}',
                                'PR-{YYYYMM}-{SEQ}' => 'PR-{YYYYMM}-{SEQ}',
                            ];

                            return $existing + $defaults;
                        })
                        ->placeholder('Contoh: PR-{YYYY}-{SEQ} atau pilih dari daftar')
                        ->helperText(function (): string {
                            $count = ProcurementCategory::query()->whereNotNull('number_template')->where('number_template', '!=', '')->distinct()->count('number_template');

                            return 'Ketik pola baru atau pilih dari '.$count.' template yang sudah ada di DB. Kosong = pakai template global PR-YYYYMM-NNNN.';
                        }),
                ])
                ->columns(2)
                ->collapsible()
                ->columnSpanFull(),

            Section::make('Status')
                ->description('Aktifkan agar kategori bisa dipakai untuk PR baru. Non-aktif akan sembunyikan dari dropdown tapi data lama tetap ada.')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->schema([
                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Non-aktifkan tanpa menghapus. PR baru tidak bisa pakai kategori non-aktif.'),
                ])
                ->columnSpanFull(),
        ]);
    }
}
