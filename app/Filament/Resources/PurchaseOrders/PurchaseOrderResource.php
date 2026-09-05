<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders;

use App\Filament\Resources\PurchaseOrders\RelationManagers\GoodsReceiptsRelationManager;
use App\Filament\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Services\MultiOfficeAuthorization;
use App\Services\ReceivingService;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Purchase Orders';

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'purchase order';

    protected static ?string $pluralModelLabel = 'purchase order';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Purchase order')
                ->schema([
                    TextEntry::make('po_number')->label('PO number'),
                    TextEntry::make('purchaseRequest.pr_number')->label('PR number'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('receipt_status')
                        ->label('Receipt status')
                        ->badge()
                        ->state(fn (PurchaseOrder $record): string => app(ReceivingService::class)->status($record)),
                    TextEntry::make('total_amount')->label('Total')->money('IDR'),
                    TextEntry::make('terms')->label('Terms')->columnSpanFull(),
                    TextEntry::make('notes')->label('Notes')->columnSpanFull(),
                ])->columns(3),
            TextEntry::make('items')
                ->label('Items')
                ->state(fn (PurchaseOrder $record): string => $record->items
                    ->map(fn ($item): string => sprintf('%s × %s @ %s = %s', $item->item_name, $item->quantity, $item->unit_price, $item->line_total))
                    ->implode('; '))
                ->columnSpanFull(),
        ]);
    }

    public static function getRelations(): array
    {
        return [GoodsReceiptsRelationManager::class];
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table);
    }

    /** @return Builder<PurchaseOrder> */
    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return parent::getEloquentQuery()->whereKey(0);
        }

        return app(MultiOfficeAuthorization::class)->scopeForCurrentContext(
            parent::getEloquentQuery()
                ->with(['purchaseRequest', 'vendor', 'quotation', 'items', 'attachments', 'goodsReceipts.items', 'goodsReceipts.receiver']),
            $user,
            'ViewAny:PurchaseOrder',
        );
    }

    public static function canAccess(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allows($user, 'ViewAny:PurchaseOrder'));
    }

    public static function canViewAny(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => app(AuthorizationService::class)->allows($user, 'ViewAny:PurchaseOrder'));
    }
}
