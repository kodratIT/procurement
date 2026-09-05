<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProcurementCategories\Pages\CreateProcurementCategory;
use App\Filament\Resources\ProcurementCategories\Pages\EditProcurementCategory;
use App\Filament\Resources\ProcurementCategories\Pages\ListProcurementCategories;
use App\Filament\Resources\ProcurementCategories\Pages\ViewProcurementCategory;
use App\Filament\Resources\ProcurementCategories\Schemas\ProcurementCategoryForm;
use App\Filament\Resources\ProcurementCategories\Schemas\ProcurementCategoryInfolist;
use App\Filament\Resources\ProcurementCategories\Tables\ProcurementCategoriesTable;
use App\Models\ProcurementCategory;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Services\MultiOfficeAuthorization;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ProcurementCategoryResource extends Resource
{
    protected static ?string $model = ProcurementCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Categories';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'kategori';

    protected static ?string $pluralModelLabel = 'kategori';

    public static function canAccess(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allows($user, 'ViewAny:ProcurementCategory'));
    }

    public static function canViewAny(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allows($user, 'ViewAny:ProcurementCategory'));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        return $user instanceof User
            ? app(MultiOfficeAuthorization::class)->scopeForCurrentContext(
                $query,
                $user,
                'ViewAny:ProcurementCategory',
            )
            : $query->whereKey(0);
    }

    public static function form(Schema $schema): Schema
    {
        return ProcurementCategoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProcurementCategoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProcurementCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProcurementCategories::route('/'),
            'create' => CreateProcurementCategory::route('/create'),
            'view' => ViewProcurementCategory::route('/{record}'),
            'edit' => EditProcurementCategory::route('/{record}/edit'),
        ];
    }
}
