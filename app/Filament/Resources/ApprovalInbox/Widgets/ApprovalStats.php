<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalInbox\Widgets;

use App\Filament\Resources\ApprovalInboxResource;
use App\Models\ApprovalHistory;
use App\Models\PurchaseRequestStatusHistory;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

final class ApprovalStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|array|null $columns = 5;

    protected function getStats(): array
    {
        $user = auth()->user();
        $base = $user instanceof User
            ? ApprovalInboxResource::activeQuery($user)
                ->select('purchase_requests.id')
                ->distinct()
            : ApprovalInboxResource::getEloquentQuery()->whereKey(0);
        $total = (clone $base)->count('purchase_requests.id');
        $pending = (clone $base)->where(function (Builder $q): void {
            $q->where('status', 'pending_approval')
                ->orWhere(function (Builder $qq): void {
                    $qq->whereNotIn('status', ['draft', 'returned', 'approved', 'completed', 'rejected', 'cancelled', 'submitted', 'procurement_review'])
                        ->where('status', 'REGEXP', '^[a-z0-9_]+$');
                });
        })->count('purchase_requests.id');
        $approved = $user instanceof User ? $this->decisionCount($user, 'approve') : 0;
        $returned = $user instanceof User ? $this->decisionCount($user, 'return') : 0;
        $rejected = $user instanceof User ? $this->decisionCount($user, 'reject') : 0;

        return [
            Stat::make('Semua Tugas Aktif', (string) $total)
                ->description('PR yang sedang menjadi tugas Anda')
                ->color('gray')
                ->icon('heroicon-o-clipboard-document-list'),
            Stat::make('Menunggu Approval', (string) $pending)
                ->description('Berada di tahap Anda saat ini')
                ->color($pending > 0 ? 'info' : 'gray')
                ->icon('heroicon-o-clock'),
            Stat::make('Disetujui', (string) $approved)
                ->description('PR yang Anda setujui')
                ->color($approved > 0 ? 'success' : 'gray')
                ->icon('heroicon-o-check-circle'),
            Stat::make('Perlu Perbaikan', (string) $returned)
                ->description('Dikembalikan oleh Anda')
                ->color($returned > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-arrow-uturn-left'),
            Stat::make('Ditolak', (string) $rejected)
                ->description('Ditolak oleh Anda')
                ->color($rejected > 0 ? 'danger' : 'gray')
                ->icon('heroicon-o-x-circle'),
        ];
    }

    private function decisionCount(User $user, string $decision): int
    {
        $ids = ApprovalHistory::query()
            ->join('approval_instance_steps', 'approval_instance_steps.id', '=', 'approval_histories.approval_instance_step_id')
            ->join('approval_instances', 'approval_instances.id', '=', 'approval_instance_steps.approval_instance_id')
            ->where('approval_histories.user_id', $user->getKey())
            ->where('approval_histories.action', $decision)
            ->pluck('approval_instances.purchase_request_id')
            ->all();

        if ($decision === 'return') {
            $ids = array_merge(
                $ids,
                PurchaseRequestStatusHistory::query()
                    ->where('actor_id', $user->getKey())
                    ->where('decision', 'return')
                    ->pluck('purchase_request_id')
                    ->all(),
            );
        }

        return collect($ids)->unique()->count();
    }
}
