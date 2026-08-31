<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Forms\DynamicFieldSchema;
use App\Filament\Resources\ProcurementReviews\Pages\EditProcurementReview;
use App\Filament\Resources\ProcurementReviews\Pages\ManageProcurementReviews;
use App\Filament\Resources\ProcurementReviews\Pages\ViewProcurementReview;
use App\Models\ProcurementField;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\AccessContextService;
use App\Services\ProcurementReviewService;
use App\Support\ProcurementPermissions;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class ProcurementReviewResource extends Resource
{
    protected static ?string $model = PurchaseRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Review Pengadaan';

    protected static ?string $modelLabel = 'review pengadaan';

    protected static ?string $pluralModelLabel = 'review pengadaan';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Konteks pengajuan')
                    ->schema([
                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->relationship('vendor', 'name', fn (Builder $query): Builder => $query->where('is_active', true))
                            ->searchable()
                            ->preload(),
                        TextInput::make('pr_number')->label('Nomor PR')->disabled()->dehydrated(false),
                        TextInput::make('requester.name')->label('Pengaju')->disabled()->dehydrated(false),
                        Hidden::make('category_id'),
                        TextInput::make('office.name')->label('Kantor')->disabled()->dehydrated(false),
                        TextInput::make('branch.name')->label('Cabang')->disabled()->dehydrated(false),
                        TextInput::make('department.name')->label('Departemen')->disabled()->dehydrated(false),
                    ])
                    ->columns(3),
                Section::make('Koreksi item')
                    ->schema([
                        Repeater::make('items')
                            ->schema([
                                Hidden::make('id'),
                                TextInput::make('item_name')->label('Item')->disabled()->dehydrated(false),
                                TextInput::make('quantity')->numeric()->minValue(0.01)->required(),
                                TextInput::make('unit_price')->label('Estimasi hasil negosiasi')->numeric()->minValue(0)->required(),
                                Textarea::make('description')->label('Deskripsi')->columnSpan(2),
                                KeyValue::make('specifications')->label('Spesifikasi')->columnSpan(2),
                                Textarea::make('notes')->label('Catatan item')->columnSpan(2),
                            ])
                            ->columns(3)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),
                Section::make('Field kategori yang dapat dikoreksi')
                    ->schema(fn (Get $get): array => app(DynamicFieldSchema::class)->components(
                        $get->integer('category_id'),
                        ProcurementField::EDITABLE_STAGE_REVIEW,
                    ))
                    ->columns(2)
                    ->columnSpanFull(),
                Textarea::make('review_reason')
                    ->label('Alasan koreksi')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('pr_number')->label('Nomor PR'),
                TextEntry::make('status')->badge(),
                TextEntry::make('requester.name')->label('Pengaju'),
                TextEntry::make('office.name')->label('Kantor'),
                TextEntry::make('vendor.name')->label('Vendor'),
                TextEntry::make('branch.name')->label('Cabang'),
                TextEntry::make('department.name')->label('Departemen'),
                TextEntry::make('category.name')->label('Kategori'),
                TextEntry::make('total_amount')->label('Total')->money('IDR'),
                TextEntry::make('reason')->label('Alasan pengajuan')->columnSpanFull(),
                TextEntry::make('items')
                    ->label('Item')
                    ->state(fn (PurchaseRequest $record): string => $record->items
                        ->map(fn ($item): string => sprintf('%s × %s (%s)', $item->item_name, $item->quantity, $item->unit_price))
                        ->implode('; '))
                    ->columnSpanFull(),
                TextEntry::make('fieldValues')
                    ->label('Field dinamis')
                    ->state(fn (PurchaseRequest $record): string => $record->fieldValues
                        ->map(fn ($value): string => $value->field_label.': '.(is_scalar($value->value) ? (string) $value->value : json_encode($value->value, JSON_THROW_ON_ERROR)))
                        ->implode('; '))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('pr_number')->label('Nomor PR')->searchable()->sortable(),
                TextColumn::make('requester.name')->label('Pengaju')->searchable(),
                TextColumn::make('office.name')->label('Kantor')->sortable(),
                TextColumn::make('branch.name')->label('Cabang'),
                TextColumn::make('department.name')->label('Departemen'),
                TextColumn::make('category.name')->label('Kategori'),
                TextColumn::make('status')->badge(),
                TextColumn::make('total_amount')->label('Total')->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')->relationship('category', 'name')->searchable()->preload(),
                SelectFilter::make('office_id')->relationship('office', 'name')->searchable()->preload(),
                SelectFilter::make('status')->options([
                    PurchaseRequest::STATUS_SUBMITTED => 'Submitted',
                    PurchaseRequest::STATUS_PROCUREMENT_REVIEW => 'Procurement review',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->authorize('review'),
                Action::make('attachments')
                    ->label('Lampiran')
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->modalHeading(fn (PurchaseRequest $record): string => 'Lampiran '.$record->pr_number)
                    ->modalContent(fn (PurchaseRequest $record): View => view(
                        'filament.procurement-review-attachments',
                        ['attachments' => $record->attachments],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->authorize('review'),
                Action::make('return')
                    ->label('Kembalikan')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')->label('Alasan pengembalian')->required(),
                    ])
                    ->authorize('return')
                    ->action(fn (PurchaseRequest $record, array $data): PurchaseRequest => app(ProcurementReviewService::class)
                        ->returnToRequester($record, (string) $data['reason'])),
                Action::make('forward')
                    ->label('Teruskan review')
                    ->color('success')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')->label('Catatan review')->nullable(),
                    ])
                    ->authorize('forward')
                    ->action(fn (PurchaseRequest $record, array $data): PurchaseRequest => app(ProcurementReviewService::class)
                        ->forward($record, (string) ($data['reason'] ?? ''))),
            ]);
    }

    /** @return Builder<PurchaseRequest> */
    public static function getEloquentQuery(): Builder
    {
        return app(ProcurementReviewService::class)->reviewQueue();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProcurementReviews::route('/'),
            'view' => ViewProcurementReview::route('/{record}'),
            'edit' => EditProcurementReview::route('/{record}/edit'),
        ];
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof PurchaseRequest && Gate::forUser(auth()->user())->allows('review', $record);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->is_active && app(AccessContextService::class)->allowedContexts($user)
            ->contains(fn ($assignment): bool => $assignment->allows(ProcurementPermissions::UPDATE));
    }
}
