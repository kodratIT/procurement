<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Forms\DynamicFieldSchema;
use App\Filament\Resources\PurchaseRequests\Pages\CreatePurchaseRequest;
use App\Filament\Resources\PurchaseRequests\Pages\EditPurchaseRequest;
use App\Filament\Resources\PurchaseRequests\Pages\ListPurchaseRequests;
use App\Filament\Resources\PurchaseRequests\Pages\ViewPurchaseRequest;
use App\Filament\Resources\PurchaseRequests\Schemas\PurchaseRequestForm;
use App\Filament\Resources\PurchaseRequests\Schemas\PurchaseRequestInfolist;
use App\Filament\Resources\PurchaseRequests\Tables\PurchaseRequestsTable;
use App\Models\ProcurementField;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Services\MultiOfficeAuthorization;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseRequestResource extends Resource
{
    protected static ?string $model = PurchaseRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Requests';

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'purchase request';

    protected static ?string $pluralModelLabel = 'purchase request';

    public static function form(Schema $schema): Schema
    {
        return PurchaseRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canAccess(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allows($user, 'ViewAny:PurchaseRequest'));
    }

    public static function canViewAny(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allows($user, 'ViewAny:PurchaseRequest'));
    }

    public static function getEloquentQuery(): Builder
    {
        // Tampilkan semua status agar PR yang sudah diajukan tetap muncul di tabel (sebelumnya hanya Draft/Returned, sehingga card & tabel tidak sinkron)
        return app(MultiOfficeAuthorization::class)->scopeForCurrentContext(
            parent::getEloquentQuery(),
            auth()->user(),
            'ViewAny:PurchaseRequest',
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseRequests::route('/'),
            'create' => CreatePurchaseRequest::route('/create'),
            'view' => ViewPurchaseRequest::route('/{record}'),
            'edit' => EditPurchaseRequest::route('/{record}/edit'),
        ];
    }

    /**
     * @return list<Component>
     */
    public static function dynamicFieldComponents(
        ?int $categoryId,
        string $stage = ProcurementField::EDITABLE_STAGE_DRAFT,
    ): array {
        return app(DynamicFieldSchema::class)->components($categoryId, $stage);
    }

    /** @return array<int|string, string> */
    private static function unitOptions(?int $itemId): array
    {
        if ($itemId === null) {
            return [];
        }

        $item = ProcurementItem::query()->with('unit')->find($itemId);

        return $item?->unit === null ? [] : [$item->unit->getKey() => $item->unit->name];
    }

    /** @return array<int|string, string> */
    private static function variantOptions(?int $itemId): array
    {
        if ($itemId === null) {
            return [];
        }

        return ProcurementVariant::query()
            ->where('item_id', $itemId)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
