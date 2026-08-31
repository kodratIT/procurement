<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ApprovalInbox\Pages\ListApprovalInbox;
use App\Filament\Resources\ApprovalInbox\Pages\ViewApprovalInbox;
use App\Models\ApprovalInstanceStep;
use App\Services\ApprovalActionService;
use App\Services\ApprovalTaskLifecycleService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class ApprovalInboxResource extends Resource
{
    protected static ?string $model = ApprovalInstanceStep::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $navigationLabel = 'Approval Inbox';

    protected static ?string $modelLabel = 'approval task';

    protected static ?string $pluralModelLabel = 'approval inbox';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Approval task')->schema([
                TextEntry::make('approvalInstance.purchaseRequest.pr_number')->label('Nomor PR'),
                TextEntry::make('label')->label('Tahap'),
                TextEntry::make('approvalInstance.workflow_reference')->label('Workflow'),
                TextEntry::make('approvalInstance.workflow_version')->label('Versi workflow'),
                TextEntry::make('status')->badge(),
                TextEntry::make('approver_role')->label('Role approver'),
                TextEntry::make('office.name')->label('Kantor'),
                TextEntry::make('branch.name')->label('Cabang'),
                TextEntry::make('department.name')->label('Departemen'),
                TextEntry::make('due_at')->dateTime()->label('Batas SLA'),
            ])->columns(3),
            Section::make('Purchase request snapshot')->schema([
                TextEntry::make('approvalInstance.purchaseRequest.requester.name')->label('Pengaju'),
                TextEntry::make('approvalInstance.purchaseRequest.category.name')->label('Kategori'),
                TextEntry::make('approvalInstance.purchaseRequest.total_amount')->money('IDR')->label('Total'),
                TextEntry::make('approvalInstance.purchaseRequest.reason')->label('Alasan')->columnSpanFull(),
                TextEntry::make('approvalInstance.purchaseRequest.items')->label('Item')->state(function (ApprovalInstanceStep $record): string {
                    return $record->approvalInstance?->purchaseRequest?->items
                        ->map(fn ($item): string => sprintf('%s × %s (%s)', $item->item_name, $item->quantity, $item->unit_price))
                        ->implode('; ') ?? '-';
                })->columnSpanFull(),
                TextEntry::make('approvalInstance.purchaseRequest.attachments')->label('Lampiran')->state(function (ApprovalInstanceStep $record): string {
                    return $record->approvalInstance?->purchaseRequest?->attachments
                        ->map(fn ($attachment): string => $attachment->original_name ?? $attachment->path)
                        ->implode('; ') ?? '-';
                })->columnSpanFull(),
            ])->columns(3),
            Section::make('Finance evidence')->schema([
                TextEntry::make('approvalInstance.purchaseRequest.quotations')->label('Quotation')->state(function (ApprovalInstanceStep $record): string {
                    return $record->approvalInstance?->purchaseRequest?->quotations
                        ->map(fn ($quotation): string => sprintf(
                            '%s — %s — %s',
                            $quotation->vendor?->name ?? 'Vendor',
                            $quotation->total_amount,
                            $quotation->notes ?: '-',
                        ))
                        ->implode("\n") ?: 'Belum ada quotation.';
                })->columnSpanFull(),
                TextEntry::make('approvalInstance.purchaseRequest.quotationRecommendations')->label('Rekomendasi')->state(function (ApprovalInstanceStep $record): string {
                    return $record->approvalInstance?->purchaseRequest?->quotationRecommendations
                        ->map(fn ($recommendation): string => sprintf(
                            '%s — %s',
                            $recommendation->vendor?->name ?? 'Vendor',
                            $recommendation->reason,
                        ))
                        ->implode("\n") ?: 'Belum ada rekomendasi.';
                })->columnSpanFull(),
                TextEntry::make('approvalInstance.purchaseRequest.statusHistories')->label('Perubahan PR')->state(function (ApprovalInstanceStep $record): string {
                    return $record->approvalInstance?->purchaseRequest?->statusHistories
                        ->map(fn ($history): string => sprintf(
                            '%s — %s — %s',
                            $history->created_at?->format('Y-m-d H:i:s'),
                            $history->event,
                            $history->note ?: $history->decision ?: '-',
                        ))
                        ->implode("\n") ?: 'Belum ada perubahan.';
                })->columnSpanFull(),
                TextEntry::make('budget_context')->label('Konteks budget')->state(function (ApprovalInstanceStep $record): string {
                    $request = $record->approvalInstance?->purchaseRequest;
                    $required = (bool) data_get($record->context, 'workflow_settings.budget_check.required', false);

                    return sprintf(
                        'Pemilik: %s — Nominal: %s — Pemeriksaan wajib: %s',
                        $request?->costCenter?->office?->name ?? $request?->office?->name ?? '-',
                        $request?->total_amount ?? '0.00',
                        $required ? 'Ya' : 'Tidak',
                    );
                })->columnSpanFull(),
                TextEntry::make('workflow_snapshot')->label('Snapshot workflow')->state(function (ApprovalInstanceStep $record): string {
                    return json_encode(data_get($record->approvalInstance?->context, 'workflow_snapshot', []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
                })->columnSpanFull(),
            ])->columns(2),
            Section::make('Approval history')->schema([
                TextEntry::make('approvalInstance.histories')->label('Riwayat keputusan')->state(function (ApprovalInstanceStep $record): string {
                    return $record->approvalInstance?->histories
                        ->map(fn ($history): string => sprintf(
                            '%s — %s — %s — %s',
                            $history->acted_at?->format('Y-m-d H:i:s'),
                            $history->action,
                            $history->actor?->name ?? 'System',
                            $history->notes ?: '-',
                        ))
                        ->implode("\n") ?: 'Belum ada keputusan.';
                })->columnSpanFull(),
            ]),
            TextColumn::make('label')->label('Tahap')->searchable(),
            TextColumn::make('approvalInstance.purchaseRequest.office.name')->label('Kantor')->sortable(),
            TextColumn::make('approvalInstance.purchaseRequest.branch.name')->label('Cabang'),
            TextColumn::make('approvalInstance.purchaseRequest.category.name')->label('Kategori'),
            TextColumn::make('approvalInstance.purchaseRequest.total_amount')->label('Total')->sortable(),
            TextColumn::make('due_at')->dateTime()->label('SLA')->sortable(),
        ])
            ->filters([
                SelectFilter::make('office_id')->relationship('approvalInstance.purchaseRequest.office', 'name')->searchable()->preload(),
                SelectFilter::make('status')->options(['pending' => 'Pending']),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->requiresConfirmation()
                    ->schema([Textarea::make('notes')->label('Catatan')->required()])
                    ->authorize(fn (ApprovalInstanceStep $record): bool => Gate::forUser(auth()->user())->allows('view', $record))
                    ->action(fn (ApprovalInstanceStep $record, array $data): ApprovalInstanceStep => app(ApprovalActionService::class)->approve($record, auth()->user(), (string) $data['notes'])),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([Textarea::make('notes')->label('Catatan')->required()])
                    ->authorize(fn (ApprovalInstanceStep $record): bool => Gate::forUser(auth()->user())->allows('view', $record))
                    ->action(fn (ApprovalInstanceStep $record, array $data): ApprovalInstanceStep => app(ApprovalActionService::class)->reject($record, auth()->user(), (string) $data['notes'])),
                Action::make('return')
                    ->label('Return')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->schema([Textarea::make('notes')->label('Catatan')->required()])
                    ->authorize(fn (ApprovalInstanceStep $record): bool => Gate::forUser(auth()->user())->allows('view', $record))
                    ->action(fn (ApprovalInstanceStep $record, array $data): ApprovalInstanceStep => app(ApprovalActionService::class)->returnStep($record, auth()->user(), (string) $data['notes'])),
            ]);
    }

    /** @return Builder<ApprovalInstanceStep> */
    public static function getEloquentQuery(): Builder
    {
        return app(ApprovalTaskLifecycleService::class)->pendingTasks();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalInbox::route('/'),
            'view' => ViewApprovalInbox::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->user())->allows('viewAny', ApprovalInstanceStep::class);
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof ApprovalInstanceStep
            && Gate::forUser(auth()->user())->allows('view', $record);
    }
}
