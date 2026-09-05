<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests\Pages;

use App\Filament\Resources\PurchaseRequestResource;
use App\Filament\Resources\PurchaseRequests\Widgets\PurchaseRequestStats;
use App\Models\PurchaseRequest;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\Writer;
use Leek\FilamentHeaderFilters\Concerns\HasHeaderFilters;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListPurchaseRequests extends ListRecords
{
    use HasHeaderFilters;

    protected static string $resource = PurchaseRequestResource::class;

    /** @var array<string, int>|null */
    private ?array $statusTabCounts = null;

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua')
                ->icon('heroicon-o-rectangle-stack')
                ->badge(fn (): int => $this->statusTabCount('all')),
            'draft' => Tab::make('Draft')
                ->icon('heroicon-o-pencil-square')
                ->badge(fn (): int => $this->statusTabCount(PurchaseRequest::STATUS_DRAFT))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PurchaseRequest::STATUS_DRAFT)),
            'submitted' => Tab::make('Diajukan')
                ->icon('heroicon-o-paper-airplane')
                ->badge(fn (): int => $this->statusTabCount(PurchaseRequest::STATUS_SUBMITTED))
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PurchaseRequest::STATUS_SUBMITTED)),
            'in_progress' => Tab::make('Diproses')
                ->icon('heroicon-o-arrow-path')
                ->badge(fn (): int => $this->statusTabCount('in_progress'))
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotIn('status', [
                    ...self::settledStatuses(),
                    PurchaseRequest::STATUS_SUBMITTED,
                ])),
            'returned' => Tab::make('Dikembalikan')
                ->icon('heroicon-o-arrow-uturn-left')
                ->badge(fn (): int => $this->statusTabCount(PurchaseRequest::STATUS_RETURNED))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PurchaseRequest::STATUS_RETURNED)),
            'rejected' => Tab::make('Ditolak')
                ->icon('heroicon-o-x-circle')
                ->badge(fn (): int => $this->statusTabCount(PurchaseRequest::STATUS_REJECTED))
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PurchaseRequest::STATUS_REJECTED)),
            'approved' => Tab::make('Disetujui')
                ->icon('heroicon-o-check-circle')
                ->badge(fn (): int => $this->statusTabCount('approved'))
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_COMPLETED])),
            'cancelled' => Tab::make('Dibatalkan')
                ->icon('heroicon-o-no-symbol')
                ->badge(fn (): int => $this->statusTabCount(PurchaseRequest::STATUS_CANCELLED))
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PurchaseRequest::STATUS_CANCELLED)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $records = PurchaseRequestResource::getEloquentQuery()
                        ->where('requester_id', auth()->id() ?? 0)
                        ->with(['category', 'office', 'requester', 'costCenter'])
                        ->orderBy('updated_at', 'desc')
                        ->get();

                    $csv = Writer::createFromFileObject(new SplTempFileObject);
                    $csv->setDelimiter(',');
                    $csv->insertOne([
                        'ID', 'Nomor PR', 'Judul', 'Kategori', 'Workflow', 'Kantor', 'Pengaju', 'Status', 'Prioritas', 'Total', 'Tgl Dibutuhkan', 'Dibuat', 'Diperbarui',
                    ]);

                    foreach ($records as $r) {
                        $csv->insertOne([
                            $r->getKey(),
                            $r->pr_number ?? 'DRAFT-'.$r->getKey(),
                            $r->title ?? '—',
                            $r->category?->name ?? '—',
                            $r->category?->workflow_reference ?? '— default',
                            $r->office?->name ?? '—',
                            $r->requester?->name ?? '—',
                            $r->status,
                            $r->priority ?? '—',
                            $r->total_amount,
                            $r->required_date?->toDateString() ?? '—',
                            $r->created_at?->toDateTimeString() ?? '—',
                            $r->updated_at?->toDateTimeString() ?? '—',
                        ]);
                    }

                    return response()->streamDownload(function () use ($csv): void {
                        echo $csv->toString();
                    }, 'purchase-requests-'.now()->format('Ymd-His').'.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
            CreateAction::make()
                ->label('Buat PR')
                ->icon('heroicon-o-plus'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PurchaseRequestStats::class,
        ];
    }

    /** @return Builder<PurchaseRequest> */
    private function tabQuery(): Builder
    {
        return PurchaseRequestResource::getEloquentQuery()
            ->where('requester_id', auth()->id() ?? 0);
    }

    private function statusTabCount(string $status): int
    {
        if ($this->statusTabCounts === null) {
            $countsByStatus = $this->tabQuery()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(fn (mixed $count): int => (int) $count);

            $this->statusTabCounts = [
                'all' => $countsByStatus->sum(),
                PurchaseRequest::STATUS_DRAFT => $countsByStatus->get(PurchaseRequest::STATUS_DRAFT, 0),
                PurchaseRequest::STATUS_SUBMITTED => $countsByStatus->get(PurchaseRequest::STATUS_SUBMITTED, 0),
                'in_progress' => $countsByStatus->except([
                    ...self::settledStatuses(),
                    PurchaseRequest::STATUS_SUBMITTED,
                ])->sum(),
                PurchaseRequest::STATUS_RETURNED => $countsByStatus->get(PurchaseRequest::STATUS_RETURNED, 0),
                PurchaseRequest::STATUS_REJECTED => $countsByStatus->get(PurchaseRequest::STATUS_REJECTED, 0),
                'approved' => $countsByStatus->get(PurchaseRequest::STATUS_APPROVED, 0)
                    + $countsByStatus->get(PurchaseRequest::STATUS_COMPLETED, 0),
                PurchaseRequest::STATUS_CANCELLED => $countsByStatus->get(PurchaseRequest::STATUS_CANCELLED, 0),
            ];
        }

        return $this->statusTabCounts[$status] ?? 0;
    }

    /** @return list<string> */
    private static function settledStatuses(): array
    {
        return [
            PurchaseRequest::STATUS_DRAFT,
            PurchaseRequest::STATUS_RETURNED,
            PurchaseRequest::STATUS_REJECTED,
            PurchaseRequest::STATUS_APPROVED,
            PurchaseRequest::STATUS_COMPLETED,
            PurchaseRequest::STATUS_CANCELLED,
        ];
    }
}
