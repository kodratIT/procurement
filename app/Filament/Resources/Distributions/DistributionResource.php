<?php

declare(strict_types=1);

namespace App\Filament\Resources\Distributions;

use App\Filament\Resources\Distributions\Pages\CreateDistribution;
use App\Filament\Resources\Distributions\Pages\ListDistributions;
use App\Filament\Resources\Distributions\Pages\ViewDistribution;
use App\Filament\Resources\Distributions\RelationManagers\PilgrimAllocationsRelationManager;
use App\Filament\Resources\Distributions\Schemas\DistributionForm;
use App\Filament\Resources\Distributions\Schemas\DistributionInfolist;
use App\Filament\Resources\Distributions\Tables\DistributionsTable;
use App\Models\Distribution;
use App\Models\User;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class DistributionResource extends Resource
{
    protected static ?string $model = Distribution::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Distributions';

    protected static ?string $modelLabel = 'distribution';

    protected static ?string $pluralModelLabel = 'distributions';

    public static function form(Schema $schema): Schema
    {
        return DistributionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DistributionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DistributionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [PilgrimAllocationsRelationManager::class];
    }

    /** @return Builder<Distribution> */
    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return parent::getEloquentQuery()->whereKey(0);
        }

        return parent::getEloquentQuery()
            ->with([
                'batch.office',
                'items.procurementItem.unit',
                'pilgrimAllocations.pilgrim',
                'pilgrimAllocations.distributionItem.procurementItem',
            ])
            ->whereHas('batch', function (Builder $query) use ($user): void {
                app(MultiOfficeAuthorization::class)->scopeForUser(
                    $query,
                    $user,
                    ProcurementPermissions::VIEW,
                );
            });
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && $user->is_active
            && $user->assignments()->currentlyActive()->get()->contains(
                fn ($assignment): bool => $assignment->allows(ProcurementPermissions::VIEW),
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDistributions::route('/'),
            'create' => CreateDistribution::route('/create'),
            'view' => ViewDistribution::route('/{record}'),
        ];
    }
}
