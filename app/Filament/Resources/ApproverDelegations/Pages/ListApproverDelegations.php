<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverDelegations\Pages;

use App\Filament\Resources\ApproverDelegations\ApproverDelegationResource;
use App\Filament\Resources\ApproverDelegations\Widgets\ApproverDelegationStats;
use App\Models\ApproverDelegation;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListApproverDelegations extends ListRecords
{
    protected static string $resource = ApproverDelegationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $records = ApproverDelegation::query()
                        ->with(['delegator', 'delegate'])
                        ->orderBy('valid_from', 'desc')
                        ->get();

                    $csv = Writer::createFromFileObject(new SplTempFileObject);
                    $csv->setDelimiter(',');
                    $csv->insertOne([
                        'ID', 'Original Approver', 'Delegate', 'Valid From', 'Valid Until', 'Status', 'Reason', 'Active', 'Created At',
                    ]);

                    foreach ($records as $r) {
                        $csv->insertOne([
                            $r->getKey(),
                            $r->delegator?->name ?? '—',
                            $r->delegate?->name ?? '—',
                            $r->valid_from?->toDateString() ?? '—',
                            $r->valid_until?->toDateString() ?? '∞',
                            $r->isActiveAt() ? 'Aktif' : 'Non-aktif',
                            $r->reason ?? '—',
                            $r->is_active ? 'Aktif' : 'Non-aktif',
                            $r->created_at?->toDateTimeString() ?? '—',
                        ]);
                    }

                    return response()->streamDownload(function () use ($csv): void {
                        echo $csv->toString();
                    }, 'approver-delegations-'.now()->format('Ymd-His').'.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ApproverDelegationStats::class,
        ];
    }
}
