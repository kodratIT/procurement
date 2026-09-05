<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcurementCategories\Widgets;

use App\Enums\ProcurementCategoryType;
use App\Models\ProcurementCategory;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class ProcurementCategoryStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|array|null $columns = 5;

    protected function getStats(): array
    {
        $total = ProcurementCategory::query()->count();
        $active = ProcurementCategory::query()->where('is_active', true)->count();
        $inactive = $total - $active;

        $goods = ProcurementCategory::query()->where('type', ProcurementCategoryType::Goods->value)->count();
        $service = ProcurementCategory::query()->where('type', ProcurementCategoryType::Service->value)->count();
        $mixed = ProcurementCategory::query()->where('type', ProcurementCategoryType::GoodsAndServices->value)->count();

        $withWorkflow = ProcurementCategory::query()->whereNotNull('workflow_reference')->where('workflow_reference', '!=', '')->count();
        $withoutWorkflow = $total - $withWorkflow;

        $needsHeavy = ProcurementCategory::query()
            ->where(function ($q): void {
                $q->where('requires_batch', true)
                    ->orWhere('requires_quotation', true)
                    ->orWhere('requires_vendor', true);
            })
            ->count();

        return [
            Stat::make('Total Kategori', (string) $total)
                ->description('Semua kategori')
                ->color('gray')
                ->icon('heroicon-o-tag'),
            Stat::make('Aktif', (string) $active)
                ->description($inactive.' non-aktif')
                ->color($active > 0 ? 'success' : 'danger')
                ->icon('heroicon-o-check-circle'),
            Stat::make('Tipe Barang/Jasa', $goods.' / '.$service.' / '.$mixed)
                ->description('Barang / Jasa / Campur')
                ->color('info')
                ->icon('heroicon-o-cube'),
            Stat::make('Pakai Workflow', (string) $withWorkflow)
                ->description($withoutWorkflow.' pakai default')
                ->color($withWorkflow > 0 ? 'primary' : 'gray')
                ->icon('heroicon-o-cog-6-tooth'),
            Stat::make('Butuh Validasi Berat', (string) $needsHeavy)
                ->description('Batch / Vendor / Quotation wajib')
                ->color($needsHeavy > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clipboard-document-check'),
        ];
    }
}
