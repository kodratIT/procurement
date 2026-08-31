<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders;

use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\RelationManagers\GoodsReceiptsRelationManager;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Filament\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\MultiOfficeAuthorization;
use App\Services\ReceivingService;
use App\Support\ProcurementPermissions;
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

    protected static ?string $navigationLabel = 'Purchase Order';

    protected static ?string $modelLabel = 'purchase order';

    protected static ?string $pluralModelLabel = 'purchase order';

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
    }

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

        return app(MultiOfficeAuthorization::class)->scopeForUser(
            parent::getEloquentQuery()
                ->with(['purchaseRequest', 'vendor', 'quotation', 'items', 'attachments', 'revisions', 'goodsReceipts.items', 'goodsReceipts.receiver']),
            $user,
            ProcurementPermissions::VIEW,
        );
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
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'view' => ViewPurchaseOrder::route('/{record}'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
