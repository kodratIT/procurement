<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests\Schemas;

use App\Models\ProcurementCategory;
use App\Models\ProcurementItem;
use App\Models\ProcurementVariant;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use App\Services\AttachmentService;
use App\Support\ProcurementPermissions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

final class PurchaseRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Konteks Organisasi & Kategori')
                ->description('Pilih assignment target — kantor, cabang, dan departemen akan disimpan dari assignment yang dipilih.')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->schema([
                    TextInput::make('requester_display')
                        ->label('Pengaju')
                        ->default(fn (): ?string => auth()->user()?->name)
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('—')
                        ->prefixIcon(Heroicon::OutlinedUser),
                    Select::make('assignment_id')
                        ->label('Assignment Target')
                        ->options(fn (): array => self::assignmentOptions())
                        ->default(fn (): ?int => self::singleAssignmentId())
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->live()
                        ->visibleOn('create')
                        ->helperText('Pilih assignment yang memiliki izin membuat PR.'),
                    TextInput::make('office_display')
                        ->label('Kantor Aktif')
                        ->default(fn (): ?string => app(AccessContextService::class)->office()?->name)
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('—')
                        ->prefixIcon(Heroicon::OutlinedBuildingOffice2),
                    TextInput::make('branch_display')
                        ->label('Cabang Aktif')
                        ->default(fn (): ?string => app(AccessContextService::class)->branch()?->name)
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('—')
                        ->prefixIcon(Heroicon::OutlinedMapPin),
                    TextInput::make('department_display')
                        ->label('Departemen Aktif')
                        ->default(fn (): ?string => app(AccessContextService::class)->department()?->name)
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('—')
                        ->prefixIcon(Heroicon::OutlinedUserGroup),
                    Select::make('cost_center_id')
                        ->label('Cost Center')
                        ->relationship('costCenter', 'name', fn (Builder $query): Builder => $query->where('is_active', true))
                        ->searchable()
                        ->preload()
                        ->placeholder('— Pilih cost center (opsional)')
                        ->helperText('Ambils dari DB cost_centers aktif.')
                        ->native(false),
                    Select::make('category_id')
                        ->label('Kategori')
                        ->relationship('category', 'name', fn (Builder $query): Builder => $query->where('is_active', true)->orderBy('name'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required()
                        ->native(false)
                        ->placeholder('— Pilih kategori')
                        ->helperText(fn (Get $get): string => self::categoryHelperText($get))
                        ->hintIcon(Heroicon::OutlinedTag),
                    Select::make('umrah_batch_id')
                        ->label('Batch Umrah')
                        ->relationship('umrahBatch', 'name', fn (Builder $query): Builder => $query->where('is_active', true)->orderBy('departure_date'))
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->placeholder('— Opsional')
                        ->helperText('Opsional. Diambil dari DB umrah_batches aktif.'),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Detail Permintaan')
                ->description('Judul, tanggal kebutuhan, dan prioritas. Alasan wajib diisi untuk audit.')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->schema([
                    TextInput::make('title')
                        ->label('Judul')
                        ->maxLength(255)
                        ->placeholder('Contoh: Pengadaan Brosur Marketing Q1')
                        ->helperText('Judul singkat yang tampil di daftar PR & approval.')
                        ->columnSpanFull(),
                    DatePicker::make('required_date')
                        ->label('Tanggal Kebutuhan')
                        ->native(false)
                        ->placeholder('Pilih tanggal')
                        ->helperText('Kapan barang/jasa dibutuhkan.'),
                    Select::make('priority')
                        ->label('Prioritas')
                        ->options([
                            'low' => 'Rendah',
                            'normal' => 'Normal',
                            'high' => 'Tinggi',
                            'urgent' => 'Mendesak',
                        ])
                        ->default('normal')
                        ->required()
                        ->native(false)
                        ->helperText('Mendesak akan highlight di tabel & notifikasi.'),
                    Textarea::make('reason')
                        ->label('Alasan / Kebutuhan')
                        ->required()
                        ->rows(3)
                        ->placeholder('Jelaskan mengapa pengadaan ini diperlukan...')
                        ->helperText('Wajib. Audit akan menampilkan alasan ini.')
                        ->columnSpanFull(),
                    Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(2)
                        ->placeholder('Catatan tambahan (opsional)')
                        ->helperText('Opsional. Tampilkan di infolist & approval.')
                        ->columnSpanFull(),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Item yang Diminta')
                ->description('Pilih dari katalog real DB atau isi manual. Total akan dihitung otomatis.')
                ->icon(Heroicon::OutlinedCube)
                ->schema([
                    Repeater::make('items')
                        ->label('Items')
                        ->schema([
                            Select::make('procurement_item_id')
                                ->label('Item Katalog')
                                ->placeholder(fn (Get $get): string => self::itemPlaceholder($get))
                                ->options(function (Get $get): array {
                                    $categoryId = $get->integer('../../category_id', isNullable: true)
                                        ?? $get->integer('../../../category_id', isNullable: true)
                                        ?? $get->integer('category_id', isNullable: true);

                                    if ($categoryId === null || $categoryId === 0) {
                                        return [];
                                    }

                                    return ProcurementItem::query()
                                        ->active()
                                        ->where('category_id', $categoryId)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all();
                                })
                                ->searchable()
                                ->preload()
                                ->live()
                                ->native(false)
                                ->columnSpanFull()
                                ->helperText(fn (Get $get): string => self::itemHelperText($get))
                                ->disabled(fn (Get $get): bool => ($get->integer('../../category_id', isNullable: true) ?? $get->integer('../../../category_id', isNullable: true) ?? $get->integer('category_id', isNullable: true)) === null),
                            Select::make('procurement_unit_id')
                                ->label('Satuan')
                                ->options(fn (Get $get): array => self::unitOptions($get->integer('procurement_item_id')))
                                ->disabled(fn (Get $get): bool => $get->integer('procurement_item_id') === 0)
                                ->native(false)
                                ->columnSpan(6)
                                ->helperText('Otomatis dari item.'),
                            Select::make('procurement_variant_id')
                                ->label('Varian')
                                ->options(fn (Get $get): array => self::variantOptions($get->integer('procurement_item_id')))
                                ->disabled(fn (Get $get): bool => $get->integer('procurement_item_id') === 0)
                                ->native(false)
                                ->columnSpan(6)
                                ->helperText('Otomatis dari item.'),
                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->minValue(0.01)
                                ->required()
                                ->columnSpan(6)
                                ->placeholder('0.00')
                                ->helperText('Minimal 0.01.'),
                            TextInput::make('unit_price')
                                ->label('Estimasi Harga Satuan')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->prefix('Rp')
                                ->columnSpan(6)
                                ->helperText('Estimasi, bukan harga final.'),
                            TextInput::make('item_name')
                                ->label('Nama Bebas')
                                ->placeholder('Wajib jika item katalog kosong')
                                ->helperText('Isi hanya jika item katalog tidak dipilih.')
                                ->maxLength(255)
                                ->required(fn (Get $get): bool => $get->integer('procurement_item_id') === 0)
                                ->visible(fn (Get $get): bool => $get->integer('procurement_item_id') === 0)
                                ->columnSpan(6),
                            Textarea::make('description')
                                ->label('Deskripsi')
                                ->rows(2)
                                ->visible(fn (Get $get): bool => $get->integer('procurement_item_id') === 0)
                                ->columnSpan(6)
                                ->placeholder('Deskripsi manual...'),
                            KeyValue::make('specifications')
                                ->label('Spesifikasi')
                                ->keyLabel('Key')
                                ->valueLabel('Value')
                                ->columnSpanFull()
                                ->helperText('Key-value bebas, tampil di infolist.'),
                            Textarea::make('notes')
                                ->label('Catatan Item')
                                ->rows(2)
                                ->columnSpanFull()
                                ->placeholder('Catatan per item (opsional)'),
                        ])
                        ->columns(12)
                        ->defaultItems(1)
                        ->reorderable()
                        ->collapsible()
                        ->columnSpanFull()
                        ->addActionLabel('Tambah item')
                        ->helperText('Minimal 1 item. Data diambil dari DB real items/units/variants.'),
                ])
                ->columnSpanFull(),

            Section::make('Lampiran')
                ->description('Upload bukti pendukung. Tipe & ukuran dari konfigurasi attachment service.')
                ->icon(Heroicon::OutlinedPaperClip)
                ->schema([
                    FileUpload::make('attachments')
                        ->label('Lampiran')
                        ->multiple()
                        ->storeFiles(false)
                        ->default(fn (?PurchaseRequest $record): array => $record?->attachments()->pluck('path')->all() ?? [])
                        ->visibility('private')
                        ->acceptedFileTypes(app(AttachmentService::class)->allowedMimeTypes())
                        ->maxSize((int) ceil(config('filesystems.attachments.max_size_bytes', AttachmentService::DEFAULT_MAX_SIZE_BYTES) / 1024))
                        ->helperText('Seret file atau klik. Tipe diizinkan sesuai service.')
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->columnSpanFull(),

            Section::make('Aksi Pengiriman')
                ->description('Simpan sebagai draf untuk dilengkapi nanti, atau ajukan langsung ke Pengadaan.')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (?PurchaseRequest $record): bool => $record === null || $record->status === PurchaseRequest::STATUS_DRAFT)
                ->schema([
                    ToggleButtons::make('submit_action')
                        ->label('Pilihan')
                        ->options([
                            'draft' => 'Simpan sebagai Draf',
                            'submit' => 'Ajukan Langsung',
                        ])
                        ->icons([
                            'draft' => Heroicon::OutlinedArchiveBox,
                            'submit' => Heroicon::OutlinedPaperAirplane,
                        ])
                        ->colors([
                            'draft' => 'gray',
                            'submit' => 'primary',
                        ])
                        ->inline()
                        ->grouped()
                        ->default('draft')
                        ->required()
                        ->live()
                        ->helperText('Draf tetap tersimpan dan bisa diajukan lewat tombol Ajukan di daftar. Ajukan langsung akan validasi final dan kirim ke review → approval.'),
                ])
                ->columnSpanFull(),
        ]);
    }

    private static function categoryHelperText(Get $get): string
    {
        $categoryId = $get->integer('category_id');
        if ($categoryId === 0) {
            $count = ProcurementCategory::query()->where('is_active', true)->count();

            return 'Pilih dari '.$count.' kategori aktif di DB. Workflow & penomoran mengikuti kategori.';
        }

        $category = ProcurementCategory::query()->find($categoryId);
        if (! $category) {
            return 'Kategori tidak ditemukan.';
        }

        $workflow = $category->workflow_reference ? 'Workflow: '.$category->workflow_reference : 'Workflow: default';
        $template = $category->number_template ? ' • Template: '.$category->number_template : '';

        return $workflow.$template.' • Tipe: '.$category->type->label();
    }

    /** @return array<int|string, string> */
    private static function assignmentOptions(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        return UserAssignment::query()
            ->where('user_id', $user->getKey())
            ->currentlyActive()
            ->with(['office', 'branch', 'department', 'assignedRole'])
            ->get()
            ->filter(fn (UserAssignment $assignment): bool => $assignment->allows(ProcurementPermissions::CREATE))
            ->mapWithKeys(fn (UserAssignment $assignment): array => [
                $assignment->getKey() => implode(' · ', array_filter([
                    $assignment->office?->name,
                    $assignment->branch?->name,
                    $assignment->department?->name,
                    $assignment->assignedRole?->name,
                ])),
            ])
            ->all();
    }

    private static function singleAssignmentId(): ?int
    {
        $options = self::assignmentOptions();

        return count($options) === 1 ? (int) array_key_first($options) : null;
    }

    private static function itemPlaceholder(Get $get): string
    {
        $categoryId = $get->integer('../../category_id', isNullable: true)
            ?? $get->integer('../../../category_id', isNullable: true)
            ?? $get->integer('category_id', isNullable: true);

        if ($categoryId === null || $categoryId === 0) {
            return '— Pilih kategori dahulu';
        }

        $category = ProcurementCategory::query()->find($categoryId);
        $count = ProcurementItem::query()->active()->where('category_id', $categoryId)->count();

        if ($count === 0) {
            return '— Tidak ada item untuk '.($category?->name ?? 'kategori ini').' (isi manual)';
        }

        return '— Pilih '.$count.' item untuk '.($category?->name ?? 'kategori ini');
    }

    private static function itemHelperText(Get $get): string
    {
        $categoryId = $get->integer('../../category_id', isNullable: true)
            ?? $get->integer('../../../category_id', isNullable: true)
            ?? $get->integer('category_id', isNullable: true);

        if ($categoryId === null || $categoryId === 0) {
            return 'Pilih kategori di atas dulu — katalog akan terfilter otomatis.';
        }

        $count = ProcurementItem::query()->active()->where('category_id', $categoryId)->count();
        $category = ProcurementCategory::query()->find($categoryId);
        $catName = $category?->name ?? 'kategori ini';

        if ($count === 0) {
            return 'Tidak ada item katalog untuk '.$catName.' — silakan isi Nama Bebas atau buat item di Master Data → Items.';
        }

        return 'Hanya menampilkan '.$count.' item aktif untuk '.$catName.' (dari DB). Kosong = isi manual.';
    }

    /** @return array<int|string, string> */
    private static function unitOptions(?int $itemId): array
    {
        if ($itemId === null || $itemId === 0) {
            return [];
        }

        $item = ProcurementItem::query()->with('unit')->find($itemId);

        return $item?->unit === null ? [] : [$item->unit->getKey() => $item->unit->name];
    }

    /** @return array<int|string, string> */
    private static function variantOptions(?int $itemId): array
    {
        if ($itemId === null || $itemId === 0) {
            return [];
        }

        return ProcurementVariant::query()
            ->where('item_id', $itemId)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
