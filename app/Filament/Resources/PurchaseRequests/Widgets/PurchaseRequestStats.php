<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests\Widgets;

use App\Filament\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use App\Services\MultiOfficeAuthorization;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

final class PurchaseRequestStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|array|null $columns = 5;

    private function scopedQuery(): Builder
    {
        $query = PurchaseRequest::query();
        $user = auth()->user();

        if (! $user) {
            return $query->whereKey(0);
        }

        $query->where('requester_id', $user->getKey());

        // Statistik mengikuti hasil agregat lintas assignment milik requester yang sama.
        return app(MultiOfficeAuthorization::class)->scopeForUser($query, $user, 'ViewAny:PurchaseRequest');
    }

    protected function getStats(): array
    {
        $base = $this->scopedQuery();

        $total = (clone $base)->count();
        $draft = (clone $base)->where('status', PurchaseRequest::STATUS_DRAFT)->count();
        // Dynamic: include any workflow stage (step_key) as in-process, not just hardcode pending_approval
        $submitted = (clone $base)->where(function (Builder $q): void {
            $q->whereIn('status', [PurchaseRequest::STATUS_SUBMITTED, PurchaseRequest::STATUS_PROCUREMENT_REVIEW, PurchaseRequest::STATUS_PENDING_APPROVAL])
                ->orWhere(function (Builder $qq): void {
                    $qq->whereNotIn('status', [PurchaseRequest::STATUS_DRAFT, PurchaseRequest::STATUS_RETURNED, PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_COMPLETED, PurchaseRequest::STATUS_REJECTED, PurchaseRequest::STATUS_CANCELLED])
                        ->where('status', 'REGEXP', '^[a-z0-9_]+$');
                });
        })->count();
        $returned = (clone $base)->where('status', PurchaseRequest::STATUS_RETURNED)->count();
        $approved = (clone $base)->whereIn('status', [PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_COMPLETED])->count();

        $marketing = (clone $base)->whereHas('category', fn (Builder $q) => $q->whereIn('code', ['MKT-GENERAL', 'mkt-gen'])->orWhere('workflow_reference', 'marketing-only'))->count();

        return [
            Stat::make('Total PR', (string) $total)
                ->description('Semua purchase request')
                ->color('gray')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(PurchaseRequestResource::getUrl('index')),
            Stat::make('Draft', (string) $draft)
                ->description('Menunggu diajukan')
                ->color($draft > 0 ? 'gray' : 'success')
                ->icon('heroicon-o-pencil-square')
                ->url(PurchaseRequestResource::getUrl('index', ['tableFilters' => ['status' => ['value' => PurchaseRequest::STATUS_DRAFT]]])),
            Stat::make('Diajukan / Review', (string) $submitted)
                ->description('Dalam proses approval')
                ->color($submitted > 0 ? 'info' : 'gray')
                ->icon('heroicon-o-clock')
                ->url(PurchaseRequestResource::getUrl('index', ['tableFilters' => ['status' => ['value' => PurchaseRequest::STATUS_SUBMITTED]]])),
            Stat::make('Dikembalikan', (string) $returned)
                ->description('Perlu perbaikan')
                ->color($returned > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-arrow-uturn-left')
                ->url(PurchaseRequestResource::getUrl('index', ['tableFilters' => ['status' => ['value' => PurchaseRequest::STATUS_RETURNED]]])),
            Stat::make('Disetujui', (string) $approved)
                ->description($marketing.' marketing')
                ->color($approved > 0 ? 'success' : 'gray')
                ->icon('heroicon-o-check-circle')
                ->url(PurchaseRequestResource::getUrl('index', ['tableFilters' => ['status' => ['value' => PurchaseRequest::STATUS_APPROVED]]])),
        ];
    }
}
