<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalInbox\Pages;

use App\Filament\Resources\ApprovalInbox\Widgets\ApprovalStats;
use App\Filament\Resources\ApprovalInboxResource;
use App\Models\User;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Leek\FilamentHeaderFilters\Concerns\HasHeaderFilters;

final class ListApprovalInbox extends ListRecords
{
    use HasHeaderFilters;

    protected static string $resource = ApprovalInboxResource::class;

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return [
            'active' => Tab::make('Aktif')
                ->icon('heroicon-o-inbox')
                ->badge(fn (): int => ApprovalInboxResource::activeQuery($user)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => ApprovalInboxResource::scopeToActive($query, $user)),
            'archive' => Tab::make('Arsip')
                ->icon('heroicon-o-archive-box')
                ->badge(fn (): int => ApprovalInboxResource::archiveQuery($user)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => ApprovalInboxResource::scopeToArchive($query, $user)),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ApprovalStats::class,
        ];
    }
}
