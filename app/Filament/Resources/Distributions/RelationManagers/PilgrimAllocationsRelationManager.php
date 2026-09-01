<?php

declare(strict_types=1);

namespace App\Filament\Resources\Distributions\RelationManagers;

use App\Models\Attachment;
use App\Models\Distribution;
use App\Models\DistributionItem;
use App\Models\Pilgrim;
use App\Models\PilgrimDistributionItem;
use App\Services\AttachmentService;
use App\Services\DistributionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PilgrimAllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'pilgrimAllocations';

    protected static ?string $title = 'Pilgrim receipts';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('distributionItem.procurementItem.name')->label('Item')->searchable(),
                TextColumn::make('pilgrim.name')->label('Pilgrim')->searchable(),
                TextColumn::make('quantity')->label('Quantity')->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('attachments_count')->counts('attachments')->label('Evidence'),
                TextColumn::make('updated_at')->label('Updated')->dateTime()->sortable(),
            ])
            ->headerActions([
                Action::make('recordReceipt')
                    ->label('Record pilgrim receipt')
                    ->authorize('create')
                    ->visible(fn (): bool => $this->ownerIsIndividual())
                    ->schema($this->receiptSchema())
                    ->action(function (array $data): PilgrimDistributionItem {
                        $item = DistributionItem::query()
                            ->where('distribution_id', $this->ownerDistribution()?->getKey())
                            ->findOrFail((int) $data['distribution_item_id']);
                        $pilgrim = Pilgrim::query()
                            ->withoutGlobalScopes()
                            ->findOrFail((int) $data['pilgrim_id']);

                        return app(DistributionService::class)->recordPilgrimReceipt(
                            $item,
                            $pilgrim,
                            $this->serviceData($data),
                            auth()->user(),
                        );
                    }),
            ])
            ->recordActions([
                Action::make('editReceipt')
                    ->label('Update receipt')
                    ->authorize('update')
                    ->visible(fn (): bool => $this->ownerIsIndividual())
                    ->schema($this->updateSchema())
                    ->action(fn (PilgrimDistributionItem $record, array $data): PilgrimDistributionItem => app(DistributionService::class)->updatePilgrimReceipt(
                        $record->distributionItem,
                        $record->pilgrim,
                        $data,
                        auth()->user(),
                    )),
                Action::make('confirmReceipt')
                    ->label('Confirm')
                    ->requiresConfirmation()
                    ->authorize('update')
                    ->visible(fn (PilgrimDistributionItem $record): bool => $this->ownerIsIndividual()
                        && $record->status !== PilgrimDistributionItem::STATUS_RECEIVED)
                    ->action(fn (PilgrimDistributionItem $record): PilgrimDistributionItem => app(DistributionService::class)->confirmPilgrimReceipt(
                        $record->distributionItem,
                        $record->pilgrim,
                        [],
                        auth()->user(),
                    )),
                Action::make('rejectReceipt')
                    ->label('Reject')
                    ->requiresConfirmation()
                    ->authorize('update')
                    ->visible(fn (PilgrimDistributionItem $record): bool => $this->ownerIsIndividual()
                        && $record->status !== PilgrimDistributionItem::STATUS_REJECTED)
                    ->action(fn (PilgrimDistributionItem $record): PilgrimDistributionItem => app(DistributionService::class)->rejectPilgrimReceipt(
                        $record->distributionItem,
                        $record->pilgrim,
                        [],
                        auth()->user(),
                    )),
                Action::make('attachEvidence')
                    ->label('Attach evidence')
                    ->authorize('attachEvidence')
                    ->visible(fn (): bool => $this->ownerIsIndividual())
                    ->schema($this->evidenceSchema())
                    ->action(function (PilgrimDistributionItem $record, array $data): Attachment {
                        return app(DistributionService::class)->attachPilgrimEvidence(
                            $record,
                            $data['file'],
                            $data['type'],
                            array_filter([
                                'document_number' => $data['document_number'] ?? null,
                                'notes' => $data['notes'] ?? null,
                            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                            auth()->user(),
                        );
                    }),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    /** @return array<int, Component> */
    private function receiptSchema(): array
    {
        return [
            Select::make('distribution_item_id')
                ->label('Distribution item')
                ->options(fn (): array => $this->itemOptions())
                ->searchable()
                ->preload()
                ->required(),
            Select::make('pilgrim_id')
                ->label('Pilgrim')
                ->options(fn (): array => $this->pilgrimOptions())
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('quantity')->numeric()->minValue(0.01)->required(),
            Select::make('status')
                ->options(array_combine(PilgrimDistributionItem::STATUSES, PilgrimDistributionItem::STATUSES))
                ->default(PilgrimDistributionItem::STATUS_PENDING)
                ->required(),
            Repeater::make('evidence')
                ->label('Receipt evidence')
                ->schema([
                    Select::make('type')->options([
                        'photo' => 'Photo',
                        'surat_jalan' => 'Surat jalan',
                    ])->required(),
                    FileUpload::make('file')
                        ->required()
                        ->storeFiles(false)
                        ->visibility('private')
                        ->acceptedFileTypes(app(AttachmentService::class)->allowedMimeTypes())
                        ->maxSize((int) ceil(config('filesystems.attachments.max_size_bytes', AttachmentService::DEFAULT_MAX_SIZE_BYTES) / 1024)),
                    TextInput::make('document_number')->label('Document number'),
                    TextInput::make('notes')->label('Evidence notes'),
                ])
                ->columns(2)
                ->defaultItems(0),
        ];
    }

    /** @return array<int, Component> */
    private function updateSchema(): array
    {
        return [
            TextInput::make('quantity')->numeric()->minValue(0.01)->required(),
            Select::make('status')
                ->options(array_combine(PilgrimDistributionItem::STATUSES, PilgrimDistributionItem::STATUSES))
                ->required(),
        ];
    }

    /** @return array<int, Component> */
    private function evidenceSchema(): array
    {
        return [
            Select::make('type')->options([
                'photo' => 'Photo',
                'surat_jalan' => 'Surat jalan',
            ])->default('photo')->required(),
            FileUpload::make('file')
                ->required()
                ->storeFiles(false)
                ->visibility('private')
                ->acceptedFileTypes(app(AttachmentService::class)->allowedMimeTypes())
                ->maxSize((int) ceil(config('filesystems.attachments.max_size_bytes', AttachmentService::DEFAULT_MAX_SIZE_BYTES) / 1024)),
            TextInput::make('document_number')->label('Document number'),
            TextInput::make('notes')->label('Evidence notes'),
        ];
    }

    /** @return array<int|string, string> */
    private function itemOptions(): array
    {
        $distribution = $this->ownerDistribution();
        if (! $distribution instanceof Distribution) {
            return [];
        }

        $distribution->loadMissing('items.procurementItem.unit', 'pilgrimAllocations');
        $allocated = $distribution->pilgrimAllocations
            ->groupBy('distribution_item_id')
            ->map(fn ($allocations): string => $allocations->reduce(
                fn (string $total, PilgrimDistributionItem $allocation): string => bcadd($total, (string) $allocation->quantity, 2),
                '0.00',
            ));

        return $distribution->items->mapWithKeys(function (DistributionItem $item) use ($allocated): array {
            $remaining = bcsub((string) $item->quantity, (string) ($allocated[$item->id] ?? '0.00'), 2);

            return [
                $item->id => sprintf(
                    '%s — remaining allocation: %s%s',
                    $item->procurementItem?->name ?? 'Unknown item',
                    $remaining,
                    $item->procurementItem?->unit?->name ? ' '.$item->procurementItem->unit->name : '',
                ),
            ];
        })->all();
    }

    /** @return array<int|string, string> */
    private function pilgrimOptions(): array
    {
        $distribution = $this->ownerDistribution();
        if (! $distribution instanceof Distribution) {
            return [];
        }

        return Pilgrim::query()
            ->withoutGlobalScopes()
            ->where('umrah_batch_id', $distribution->umrah_batch_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<string, mixed> */
    private function serviceData(array $data): array
    {
        $data['evidence'] = collect($data['evidence'] ?? [])->map(fn (array $evidence): array => [
            'file' => $evidence['file'],
            'type' => $evidence['type'],
            'metadata' => array_filter([
                'document_number' => $evidence['document_number'] ?? null,
                'notes' => $evidence['notes'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ])->values()->all();

        return $data;
    }

    private function ownerDistribution(): ?Distribution
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Distribution ? $owner : null;
    }

    private function ownerIsIndividual(): bool
    {
        return $this->ownerDistribution()?->isIndividualMode() ?? false;
    }
}
